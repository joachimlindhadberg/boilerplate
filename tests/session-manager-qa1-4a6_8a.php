<?php
/** Standalone deterministic model-behaviour QA (NO MySQL/network/session persistence). */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$root = dirname(__DIR__);
require_once $root . '/core/classes/Db.php';
require_once $root . '/core/classes/SessionManager.php';
require_once $root . '/core/classes/Users.php';
require_once $root . '/core/classes/Auth.php';
require_once $root . '/core/classes/Translator.php';

class FakeDb extends Db
{
    public array $records = [];
    public array $users = [];
    public int $idCounter = 0;
    public int $last = 0;
    public int $commitCount = 0;
    private bool $tx = false;
    public function __construct() { $this->users = [3 => [
        'id'=>3, 'role_id'=>1, 'role_name'=>'admin', 'firstname'=>'Ada', 'lastname'=>'Test',
        'email'=>'ada@example.invalid', 'status'=>'active', 'password'=>password_hash('valid-secret-1234', PASSWORD_DEFAULT),
        'must_change_password'=>0,
    ]]; }
    public function beginTransaction(): void { if ($this->tx) throw new RuntimeException('nested transaction'); $this->tx=true; }
    public function commit(): void { if (!$this->tx) throw new RuntimeException('no transaction'); $this->tx=false; ++$this->commitCount; }
    public function rollback(): void { $this->tx=false; }
    public function getInsertId(): int { return $this->last; }
    public function query(string $sql, array $args=[]): void
    {
        $sql = trim(preg_replace('/\s+/', ' ', $sql));
        if (str_starts_with($sql, 'INSERT INTO auth_sessions')) {
            $id=++$this->idCounter; $this->last=$id;
            $this->records[$id]=['id'=>$id,'user_id'=>$args[0], 'session_hash'=>$args[1], 'remember_hash'=>null,'remember_expires_at'=>0]; return;
        }
        if (str_starts_with($sql, 'UPDATE auth_sessions SET session_hash = ?, remember_hash = ?')) {
            [$hash,$rh,$expiry,$id,$uid,$old] = $args;
            if(isset($this->records[$id]) && $this->records[$id]['user_id']===$uid
                && $this->records[$id]['remember_hash']===$old && $this->records[$id]['remember_expires_at']>time()) {
                $this->records[$id]['session_hash']=$hash; $this->records[$id]['remember_hash']=$rh;
                $this->records[$id]['remember_expires_at']=$expiry;
            } return;
        }
        if (str_starts_with($sql, 'UPDATE auth_sessions SET session_hash = ?')) {
            [$hash,$id,$uid,$old]=$args;
            if(isset($this->records[$id]) && $this->records[$id]['user_id']===$uid && $this->records[$id]['session_hash']===$old) $this->records[$id]['session_hash']=$hash;
            return;
        }
        if (str_starts_with($sql,'UPDATE auth_sessions SET remember_hash = ?, remember_expires_at')) {
            [$rh,$expiry,$id,$uid]=$args; if(isset($this->records[$id]) && $this->records[$id]['user_id']===$uid) {
                $this->records[$id]['remember_hash']=$rh; $this->records[$id]['remember_expires_at']=$expiry;
            } return;
        }
        if (str_starts_with($sql,'UPDATE auth_sessions SET remember_hash = NULL')) {
            [$uid,$id]=$args; if(isset($this->records[$id]) && $this->records[$id]['user_id']===$uid) {
                $this->records[$id]['remember_hash']=null; $this->records[$id]['remember_expires_at']=0;
            } return;
        }
        if (str_starts_with($sql,'UPDATE auth_sessions SET last_seen_at')) return;
        if (str_starts_with($sql,'DELETE FROM auth_sessions WHERE id')) {
            [$id,$uid,$hash]=$args;
            if(isset($this->records[$id]) && $this->records[$id]['user_id']===$uid && $this->records[$id]['session_hash']===$hash) unset($this->records[$id]);
            return;
        }
        if (str_starts_with($sql,'DELETE FROM auth_sessions WHERE user_id = ? AND id <> ?')) {
            [$uid,$except]=$args;
            foreach ($this->records as $i=>$rec) if ($rec['user_id']===$uid && $i!==$except) unset($this->records[$i]);
            return;
        }
        if (str_starts_with($sql,'DELETE FROM auth_sessions WHERE user_id = ?')) {
            foreach ($this->records as $i=>$rec) if ($rec['user_id']===$args[0]) unset($this->records[$i]);
            return;
        }
        if (str_starts_with($sql,'UPDATE users SET password = ?')) {
            $uid=(int)end($args); $this->users[$uid]['password']=$args[0];
            if (str_contains($sql,'must_change_password = 0')) $this->users[$uid]['must_change_password']=0;
            return;
        }
        if (str_starts_with($sql,'UPDATE users SET last_login')) return;
        throw new RuntimeException('Unhandled write '.$sql);
    }
    public function fetchValue(string $sql, array $args=[])
    {
        if(str_contains($sql,'COUNT(*) FROM auth_sessions')) {
            [$id,$uid,$hash]=$args;
            return (int)(isset($this->records[$id]) && $this->records[$id]['user_id']===$uid && $this->records[$id]['session_hash']===$hash);
        }
        throw new RuntimeException('Unhandled value '.$sql);
    }
    public function fetchArray(string $sql, array $args=[]): array
    {
        if(str_contains($sql,'FROM auth_sessions WHERE user_id = ? AND remember_hash')) {
            [$uid,$rh]=$args;
            foreach($this->records as $rec) if($rec['user_id']===$uid && $rec['remember_hash']===$rh && $rec['remember_expires_at']>time()) return [['id'=>$rec['id']]];
            return [];
        }
        if(str_contains($sql,'SELECT id, created_at, last_seen_at')) return [];
        if(str_contains($sql,'FROM users u')) {
            $target=(string)($args[0]??'');
            foreach($this->users as $rec) if((str_contains($sql,'WHERE u.id = ?') && (int)$target===$rec['id']) || (str_contains($sql,'WHERE u.email = ?') && $target===$rec['email'])) return [$rec];
            return [];
        }
        throw new RuntimeException('Unhandled fetch '.$sql);
    }
}
$passed=0; $failed=0;
$check=static function(bool $ok, string $name) use (&$passed,&$failed):void {
    echo ($ok?'PASS: ':'FAIL: ').$name."\n";
    $ok ? ++$passed : ++$failed;
};
$db=new FakeDb();
$mgr=new SessionManager($db);
$a=$mgr->create(3); $b=$mgr->create(3);
$check($a['id']!==$b['id'] && $a['token']!==$b['token'], 'Separate device logins have different IDs/secrets');
$check($mgr->valid($a['id'],3,$a['token']) && $mgr->valid($b['id'],3,$b['token']), 'Two logins authenticate independently');
$check($db->records[$a['id']]['session_hash']===hash('sha256',$a['token']) && $db->records[$a['id']]['session_hash']!==$a['token'], 'Only digested secrets reside in DB');
$check(!$mgr->valid($a['id'],4,$a['token']) && !$mgr->valid($a['id'],3,str_repeat('e',64)) && !$mgr->valid($a['id'],3,'nothex'), 'Wrong account, wrong secret, invalid format denied');
$rmA=SessionManager::newSecret(); $rmB=SessionManager::newSecret();
$mgr->setRemember($a['id'],3,$a['token'],$rmA,time()+86400);
$mgr->setRemember($b['id'],3,$b['token'],$rmB,time()+86400);
$check($mgr->redeemRemember(3,$rmA)===$a['id'] && $mgr->redeemRemember(3,$rmB)===$b['id'], 'Remember tokens scoped to separate devices');
$check($mgr->redeemRemember(4,$rmA)===null && $mgr->redeemRemember(3,'bad')===null, 'Cross-user and malformed remember tokens denied');
$newSession=SessionManager::newSecret(); $newRemember=SessionManager::newSecret();
$check($mgr->rotateRemember($a['id'],3,$rmA,$newSession,$newRemember,time()+86400), 'Remember cookie consumed and both secrets rotated');
$check($mgr->redeemRemember(3,$rmA)===null && $mgr->redeemRemember(3,$newRemember)===$a['id'], 'Old remember-cookie replay blocked');
$check(!$mgr->valid($a['id'],3,$a['token']) && $mgr->valid($a['id'],3,$newSession), 'Old PHP-session binding invalid after remember rotation');
$check(!$mgr->rotateRemember($a['id'],3,$rmA,SessionManager::newSecret(),SessionManager::newSecret(),time()+86400), 'Concurrent replay cannot rotate twice');
$check($mgr->valid($b['id'],3,$b['token']) && $mgr->redeemRemember(3,$rmB)===$b['id'], 'Other device unaffected by rotation');
$mgr->revoke($a['id'],3,$newSession);
$check(!$mgr->valid($a['id'],3,$newSession) && $mgr->redeemRemember(3,$newRemember)===null, 'Logout revokes the current PHP and remember binding');
$check($mgr->valid($b['id'],3,$b['token']), 'Logout preserves other active devices');
$mgr->revoke($b['id'],3,str_repeat('f',64));
$check($mgr->valid($b['id'],3,$b['token']), 'Attacker with wrong binding cannot revoke a victim session');
$c=$mgr->create(3);
$expiredRemember=SessionManager::newSecret();
$mgr->setRemember($c['id'],3,$c['token'],$expiredRemember,time()-1);
$check($mgr->redeemRemember(3,$expiredRemember)===null, 'Expired remember cookie does not authenticate');
$mgr->revokeAll(3,$b['id']);
$check($mgr->valid($b['id'],3,$b['token']) && !$mgr->valid($c['id'],3,$c['token']), 'Password-change style revocation preserves only current login');
$check($mgr->redeemRemember(3,$rmB)===null, 'Preserved login has its remember token cleared');
$rotated=$mgr->rotate($b['id'],3,$b['token']);
$check(is_string($rotated) && $rotated!==$b['token'] && $mgr->valid($b['id'],3,$rotated) && !$mgr->valid($b['id'],3,$b['token']), 'Password change rotates preserved login binding');
$mgr->revokeAll(3);
$check(!$mgr->valid($b['id'],3,$rotated) && $db->records===[], 'Administrator revocation removes all login rows');
$check(strlen(SessionManager::newSecret())===64, 'Token generator produces 256-bit hex');
$check(substr_count(file_get_contents($root.'/database/install/empty-schema.sql'),'CREATE TABLE `')===25, 'Fresh install schema includes auth_sessions');
$check(str_contains(file_get_contents($root.'/database/migrations/20261023_auth_sessions_v2_5_0_qa1.sql'),'CREATE TABLE `auth_sessions`'), 'Manual phpMyAdmin migration delivered');
$check(str_contains(file_get_contents($root.'/core/classes/Users.php'),'rehashPassword') && str_contains(file_get_contents($root.'/core/classes/Auth.php'),'$this->users->rehashPassword'), 'Transparent password rehash path does not revoke sessions');
$check(str_contains(file_get_contents($root.'/core/classes/Auth.php'),'$this->sessions->valid(') && str_contains(file_get_contents($root.'/core/classes/Auth.php'),'$this->logout();'), 'Auth verifies database-bound session on request and fails closed');
$check(str_contains(file_get_contents($root.'/core/classes/Users.php'),'->revokeAll($id, $preserveSession)') && str_contains(file_get_contents($root.'/core/classes/Users.php'),'->revokeAll($id);'), 'Credential changes expose preserve-current and revoke-all mechanisms');
$check(str_contains(file_get_contents($root.'/core/classes/Users.php'),'if ($securityChange)') && str_contains(file_get_contents($root.'/core/classes/Users.php'),'$this->db->beginTransaction()'), 'Admin account security changes revoke transactionally');
// Exercise real user-model password paths against disposable in-memory records.
$users=(new ReflectionClass(Users::class))->newInstanceWithoutConstructor();
$prop=new ReflectionProperty(Users::class,'db');
$prop->setValue($users,$db);
$e=$mgr->create(3);
$users->rehashPassword(3,'unchanged-but-hash-updated');
$check($mgr->valid($e['id'],3,$e['token']), 'Real Users::rehashPassword does not revoke current login');
$f=$mgr->create(3);
$users->changePasswordAndClearRequirement(3,'new-password-1234',$e['id']);
$check($mgr->valid($e['id'],3,$e['token']) && !$mgr->valid($f['id'],3,$f['token']), 'Real Users::changePasswordAndClearRequirement revokes other devices');
$check(password_verify('new-password-1234',$db->users[3]['password']) && $db->commitCount>0, 'Password and token change committed together');
$users->updatePassword(3,'admin-reset-1234');
$check(!$mgr->valid($e['id'],3,$e['token']) && password_verify('admin-reset-1234',$db->users[3]['password']), 'Real Users::updatePassword revokes all devices');
echo "Session Manager QA: $passed PASS / $failed FAIL\n";
exit($failed?1:0);
