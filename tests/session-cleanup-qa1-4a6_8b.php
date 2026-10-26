<?php
/** Isolated deterministic model + Auth cookie lifecycle tests. Never connects to MySQL. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
ob_start();
$root = dirname(__DIR__);
foreach (['Db','SessionManager','Users','Settings','CapabilityRegistry','Translator','Auth'] as $class) {
    require_once $root . '/core/classes/' . $class . '.php';
}

final class MemoryDb8b extends Db
{
    /** @var array<int,array<string,mixed>> */
    public array $records=[];
    public array $users=[];
    public int $counter=0;
    private int $insert=0;
    public function __construct() {
        $this->users[3]=[
            'id'=>3,'role_id'=>1,'role_name'=>'admin','firstname'=>'Ada','lastname'=>'Example',
            'email'=>'ada@example.invalid','status'=>'active',
            'password'=>password_hash('password-long-123', PASSWORD_DEFAULT),
            'must_change_password'=>0,'admin_language'=>null
        ];
    }
    public function getInsertId(): int { return $this->insert; }
    public function query(string $sql, array $a=[]): void {
        $sql=trim(preg_replace('/\s+/', ' ', $sql));
        if(str_starts_with($sql,'INSERT INTO auth_sessions')) {
            $id=++$this->counter; $this->insert=$id;
            $this->records[$id]=[
                'id'=>$id,'user_id'=>$a[0],'session_hash'=>$a[1], 'remember_hash'=>null,
                'remember_expires_at'=>null,'last_seen_at'=>time(),
            ]; return;
        }
        if(str_starts_with($sql,'UPDATE auth_sessions SET remember_hash = ?, remember_expires_at')) {
            [$rh,$until,$id,$uid]=$a;
            if(isset($this->records[$id]) && $this->records[$id]['user_id']===$uid) {
                $this->records[$id]['remember_hash']=$rh;
                $this->records[$id]['remember_expires_at']=$until;
            } return;
        }
        if(str_starts_with($sql,'UPDATE auth_sessions SET session_hash = ?, remember_hash = ?')) {
            [$sh,$rh,$until,$id,$uid,$old]=$a;
            if(isset($this->records[$id]) && $this->records[$id]['user_id']===$uid
                && $this->records[$id]['remember_hash']===$old
                && $this->records[$id]['remember_expires_at']>time()) {
                $this->records[$id]['session_hash']=$sh;
                $this->records[$id]['remember_hash']=$rh;
                $this->records[$id]['remember_expires_at']=$until;
                $this->records[$id]['last_seen_at']=time();
            } return;
        }
        if(str_starts_with($sql,'DELETE FROM auth_sessions WHERE id = ?')) {
            [$id,$uid,$sh]=$a;
            if(isset($this->records[$id]) && $this->records[$id]['user_id']===$uid
                && $this->records[$id]['session_hash']===$sh) unset($this->records[$id]);
            return;
        }
        if(str_starts_with($sql,'DELETE FROM auth_sessions WHERE user_id = ? AND remember_hash = ?')) {
            [$uid,$rh]=$a;
            foreach($this->records as $id=>$row) {
                if($row['user_id']===$uid && $row['remember_hash']===$rh) unset($this->records[$id]);
            } return;
        }
        if(str_starts_with($sql,'DELETE FROM auth_sessions WHERE last_seen_at < DATE_SUB')) {
            foreach($this->records as $id=>$row) {
                $old=$row['last_seen_at'] < time()-$a[0];
                $rememberValid=$row['remember_hash']!==null && ($row['remember_expires_at']??0)>time();
                if($old && !$rememberValid) unset($this->records[$id]);
            } return;
        }
        if(str_starts_with($sql,'UPDATE auth_sessions SET last_seen_at')) return;
        if(str_starts_with($sql,'UPDATE users SET last_login')) return;
        throw new RuntimeException('Unhandled SQL write in model: '.$sql);
    }
    public function fetchValue(string $sql, array $args=[]) {
        if(str_contains($sql,'COUNT(*) FROM auth_sessions')) {
            [$id,$uid,$sh]=$args;
            return (int)(isset($this->records[$id]) && $this->records[$id]['user_id']===$uid
                && $this->records[$id]['session_hash']===$sh);
        }
        throw new RuntimeException('Unhandled SQL value in model: '.$sql);
    }
    public function fetchArray(string $sql, array $a=[]): array {
        if(str_contains($sql,'FROM auth_sessions WHERE user_id = ? AND remember_hash')) {
            [$uid,$rh]=$a;
            foreach($this->records as $row) {
                if($row['user_id']===$uid && $row['remember_hash']===$rh
                    && $row['remember_expires_at']>time()) return [['id'=>$row['id']]];
            } return [];
        }
        throw new RuntimeException('Unhandled SQL fetch in model: '.$sql);
    }
}
final class Users8b extends Users {
    private MemoryDb8b $mem;
    public function __construct(MemoryDb8b $db) { $this->mem=$db; }
    public function findByEmail(string $email): ?array { return $this->mem->users[3]['email']===$email ? $this->mem->users[3] : null; }
    public function find(int $id): ?array { return $this->mem->users[$id]??null; }
    public function updateLastLogin(int $id): void {}
    public function rehashPassword(int $id, string $password): void { $this->mem->users[$id]['password']=password_hash($password,PASSWORD_DEFAULT); }
}
final class Settings8b extends Settings {
    public function __construct() {}
    public function get(string $key, mixed $default=null): mixed {
        return ['remember_cookie_name'=>'boilerplate_remember','remember_me_days'=>30,'session_timeout'=>1800][$key]??$default;
    }
}
final class Auth8b extends Auth {
    public function __construct(MemoryDb8b $db) {
        $this->db=$db; $this->users=new Users8b($db); $this->settings=new Settings8b();
        $this->sessions=new SessionManager($db);
    }
    protected function secureCookie(): bool { return false; }
    public function recoverFromRemember(): void { $this->rememberLogin(); }
    public function currentBinding(): ?array { return $this->binding(); }
}

$pass=0;$fail=0;
$assert=static function(bool $ok,string $label) use (&$pass,&$fail):void {
    echo ($ok?'PASS: ':'FAIL: ').$label."\n";
    $ok?++$pass:++$fail;
};
$db=new MemoryDb8b();$mgr=new SessionManager($db);$auth=new Auth8b($db);
$_SESSION=[];$_COOKIE=[];
if(session_status()!==PHP_SESSION_ACTIVE) session_start();

$other=$mgr->create(3); // Different browser must survive all local operations.
$assert($auth->login('ada@example.invalid','password-long-123',false),'Fresh password login succeeds');
$first=$auth->currentBinding();
$assert($first!==null && $mgr->valid($first['id'],3,$first['token']),'New login has a valid DB binding');
$assert($auth->login('ada@example.invalid','password-long-123',false),'Repeated password login succeeds');
$second=$auth->currentBinding();
$assert($second!==null && $second['id']!==$first['id'],'Repeat login creates distinct current credential');
$assert(!isset($db->records[$first['id']]) && isset($db->records[$other['id']]),'Re-login revokes previous bound row only; other device preserved');

// The regression: browser deletes PHPSESSID while a remember cookie survives.
$rememberRow=$mgr->create(3);$cookieSecret=SessionManager::newSecret();
$mgr->setRemember($rememberRow['id'],3,$rememberRow['token'],$cookieSecret,time()+30*86400);
$_COOKIE['boilerplate_remember']='3|'.$cookieSecret;
$_SESSION=[];
$assert($auth->login('ada@example.invalid','password-long-123',false),'Password login after lost PHPSESSID succeeds');
$assert(!isset($db->records[$rememberRow['id']]),'Remembered old row revoked despite missing PHP session');
$assert($mgr->redeemRemember(3,$cookieSecret)===null,'Old remember cookie cannot authenticate after replacement');
$assert(isset($db->records[$other['id']]),'Unrelated device remains valid after recovered login');

// A cookie's bearer secret alone is enough to revoke its EXACT row on logout.
$cookieRow=$mgr->create(3);$cookieSecret2=SessionManager::newSecret();
$mgr->setRemember($cookieRow['id'],3,$cookieRow['token'],$cookieSecret2,time()+30*86400);
$_SESSION=[];$_COOKIE['boilerplate_remember']='3|'.$cookieSecret2;
$auth->logout();
$assert(!isset($db->records[$cookieRow['id']]),'Logout revokes remember-only login even if PHPSESSID lost');
$assert(isset($db->records[$other['id']]),'Remember-only logout leaves other devices untouched');

// Simulate automatic Remember Me recovery and explicit logout, the exact user flow.
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
$_SESSION=[];
$recovery=$mgr->create(3);$recoveryCookie=SessionManager::newSecret();
$mgr->setRemember($recovery['id'],3,$recovery['token'],$recoveryCookie,time()+30*86400);
$_COOKIE['boilerplate_remember']='3|'.$recoveryCookie;
$auth->recoverFromRemember();$rotated=$auth->currentBinding();
$assert($rotated!==null && $rotated['id']===$recovery['id'],'Remember Me restores same DB row and rotates binding');
$assert($mgr->redeemRemember(3,$recoveryCookie)===null,'Consumed Remember Me token cannot be replayed');
$auth->logout();
$assert(!isset($db->records[$recovery['id']]),'Logout after Remember Me recovery removes matching DB row');

// Forged cookie may not revoke another user's remembered row.
$victim=$mgr->create(3);$victimRemember=SessionManager::newSecret();
$mgr->setRemember($victim['id'],3,$victim['token'],$victimRemember,time()+3600);
$mgr->revokeRemember(3,str_repeat('a',64));
$mgr->revokeRemember(4,$victimRemember);
$mgr->revokeRemember(3,'invalid');
$assert($mgr->valid($victim['id'],3,$victim['token']) && $mgr->redeemRemember(3,$victimRemember)===$victim['id'],'Incorrect remember secret or user cannot revoke victim');
$mgr->revokeRemember(3,$victimRemember);
$assert(!$mgr->valid($victim['id'],3,$victim['token']),'Valid remember secret revokes only its bound device');

// Lazy cleanup must never delete an unexpired remembered device.
$staleUnremembered=$mgr->create(3);$db->records[$staleUnremembered['id']]['last_seen_at']=time()-3*86400;
$staleExpired=$mgr->create(3);$mgr->setRemember($staleExpired['id'],3,$staleExpired['token'],SessionManager::newSecret(),time()-60);
$db->records[$staleExpired['id']]['last_seen_at']=time()-3*86400;
$staleRemembered=$mgr->create(3);$rememberKeep=SessionManager::newSecret();
$mgr->setRemember($staleRemembered['id'],3,$staleRemembered['token'],$rememberKeep,time()+86400);
$db->records[$staleRemembered['id']]['last_seen_at']=time()-3*86400;
$recentUnremembered=$mgr->create(3);
$mgr->cleanupAbandoned();
$assert(!isset($db->records[$staleUnremembered['id']]) && !isset($db->records[$staleExpired['id']]),'Cleanup removes old unremembered and expired remembered rows');
$assert(isset($db->records[$staleRemembered['id']]) && isset($db->records[$recentUnremembered['id']]),'Cleanup preserves unexpired remembered or recently active rows');
$assert(isset($db->records[$other['id']]),'Cleanup never affects other still-valid device');
$longTimeout=$mgr->create(3); $db->records[$longTimeout['id']]['last_seen_at']=time()-3*86400;
$mgr->cleanupAbandoned(5*86400);
$assert(isset($db->records[$longTimeout['id']]),'Cleanup respects configured 5-day session timeout');
$mgr->cleanupAbandoned();
$assert(!isset($db->records[$longTimeout['id']]),'Default timeout can safely clean an older non-remembered row');

// The 4A.6.8a SQL migration continues to be the ONLY schema step.
$assert(str_contains(file_get_contents($root.'/database/migrations/20261023_auth_sessions_v2_5_0_qa1.sql'),'auth_sessions'),'Existing migration retained, no new table needed');
$output=ob_get_clean();echo $output;
echo "4A.6.8b Session Cleanup QA: {$pass} PASS / {$fail} FAIL\n";
exit($fail?1:0);
