<?php

declare(strict_types=1);

/** F4c.5 local regression tests: SYNTHETIC only. Native MySQL runs separately. */
require_once dirname(__DIR__) . '/core/classes/UpdateNativeMigrationRehearsal.php';
require_once dirname(__DIR__) . '/core/classes/UpdateDurableRecoveryGate.php';

final class F4c5FakeTarget implements UpdateF4c5MigrationTarget
{
    private string $file;
    private string $schema;
    public bool $failDdl = false;

    public function __construct(string $root, string $schema)
    {
        $this->file = $root . '/synthetic-qa.json';
        $this->schema = $schema;
        if (!is_file($this->file)) {
            file_put_contents($this->file, json_encode(['tables'=>26,'triggers'=>9,'migrations'=>8,
                'flag'=>false,'marker'=>false], JSON_THROW_ON_ERROR));
            chmod($this->file, 0600);
        }
    }
    public function assertPrivateIdentity(string $root): void
    {
        if (!is_file($this->file) || realpath(dirname($this->file)) !== realpath($root)) {
            throw new RuntimeException('Fake fixture identity rejected.');
        }
    }
    public function schemaName(): string { return $this->schema; }
    public function inventory(): array { return json_decode((string)file_get_contents($this->file), true, 16, JSON_THROW_ON_ERROR); }
    public function executeQaStep(int $index): void
    {
        if ($this->failDdl) throw new RuntimeException('Synthetic expected DDL fault');
        $v = $this->inventory();
        if ($index === 0 && !$v['flag']) $v['flag'] = true;
        elseif ($index === 1 && !$v['marker']) { $v['marker'] = true; $v['tables']++; }
        else throw new RuntimeException('Duplicate synthetic DDL refused');
        file_put_contents($this->file, json_encode($v, JSON_THROW_ON_ERROR));
    }
}

$count = 0;
$fail = 0;
function checkF4c5(bool $ok, string $name): void
{
    global $count, $fail;
    if ($ok) { $count++; echo 'PASS: ' . $name . "\n"; }
    else { $fail++; echo 'FAIL: ' . $name . "\n"; }
}
function throwsF4c5(callable $cb): bool
{
    try { $cb(); return false; } catch (Throwable $e) { return true; }
}
function createF4c5(): array
{
    $root = '/tmp/bp-f4c5-run-' . bin2hex(random_bytes(8));
    if (!mkdir($root, 0700)) throw new RuntimeException('No private fixture');
    chmod($root, 0700);
    $fake = new F4c5FakeTarget($root, 'boilerplate_f4b1_' . bin2hex(random_bytes(8)));
    return [$root, $fake, new UpdateNativeMigrationRehearsal($root, $fake)];
}
function cleanF4c5(string $root): void
{
    $real = realpath($root);
    if (!is_string($real) || dirname($real) !== realpath('/tmp')
        || preg_match('/\Abp-f4c5-run-[a-f0-9]{16}\z/D', basename($real)) !== 1) {
        throw new RuntimeException('Unsafe QA cleanup');
    }
    $delete = static function (string $dir) use (&$delete): void {
        foreach (new DirectoryIterator($dir) as $e) {
            if ($e->isDot()) continue;
            if ($e->isLink()) throw new RuntimeException('Unexpected fixture symlink');
            if ($e->isDir()) $delete($e->getPathname());
            else unlink($e->getPathname());
        }
        rmdir($dir);
    };
    $delete($real);
}

$fixtures=[];
$hash=str_repeat('a',64);
try {
    [$root,$fake,$run]=createF4c5();$fixtures[]=$root;
    checkF4c5(throwsF4c5(fn()=>new UpdateNativeMigrationRehearsal('/tmp', $fake)), 'Generic temp root refused');
    checkF4c5(throwsF4c5(fn()=>new UpdateNativeMigrationRehearsal(dirname(__DIR__), $fake)), 'CMS source refused');
    checkF4c5(throwsF4c5(fn()=>new UpdateNativeMigrationRehearsal($root, new F4c5FakeTarget($root,'cms_production'))), 'Production-like schema refused');
    checkF4c5($run->status()['state']==='manual_recovery', 'No journal fails closed');
    checkF4c5(throwsF4c5(fn()=>$run->begin('x',$hash,$hash)), 'Unverified SHA refuses start');
    checkF4c5((new UpdateDurableRecoveryGate($root))->status()===UpdateDurableRecoveryGate::CLEAR, 'No recovery before begin');
    $result=$run->begin($hash,$hash,$hash);
    checkF4c5($result['state']==='prepared' && $result['blocked'] && !$result['install_ready'], 'Persisted prepared state');
    checkF4c5((new UpdateDurableRecoveryGate($root))->status()===UpdateDurableRecoveryGate::DATABASE, 'F4c.2 recognizes persistent database marker');
    checkF4c5(throwsF4c5(fn()=>$run->begin($hash,$hash,$hash)), 'Duplicate run refused');
    checkF4c5(throwsF4c5(fn()=>$run->verify($hash)), 'Cannot verify before DDL');
    $first=$run->step();
    checkF4c5($first['completed']===1 && $first['can_retry'], 'First synthetic step checkpointed');
    checkF4c5($fake->inventory()['flag']===true && !$fake->inventory()['marker'], 'First synthetic SQL effect visible');
    $second=$run->step();
    checkF4c5($second['completed']===2 && !$second['can_retry'], 'Second synthetic step checkpointed');
    checkF4c5($fake->inventory()['tables']===27 && $fake->inventory()['marker'], 'Second synthetic SQL effect visible');
    checkF4c5(throwsF4c5(fn()=>$run->step()), 'No third migration');
    checkF4c5(throwsF4c5(fn()=>$run->verify($hash)), 'Wrong independent inventory hash refused');
    $proof=hash('sha256',json_encode($fake->inventory(),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $verified=$run->verify($proof);
    checkF4c5($verified['state']==='verified_qa_only' && $verified['blocked'], 'Verification keeps quarantine');
    checkF4c5((new UpdateDurableRecoveryGate($root))->status()===UpdateDurableRecoveryGate::DATABASE, 'Verified run cannot clear F4c.2');
    checkF4c5(throwsF4c5(fn()=>$run->verify($proof)), 'No repeated finalization');
    checkF4c5(throwsF4c5(fn()=>$run->step()), 'No SQL after verification');
    $reboot=new UpdateNativeMigrationRehearsal($root,new F4c5FakeTarget($root,$fake->schemaName()));
    checkF4c5($reboot->status()['state']==='verified_qa_only', 'New object sees durable QA state');

    [$root2,$f2,$r2]=createF4c5();$fixtures[]=$root2;
    $r2->begin($hash,$hash,$hash);
    $before=throwsF4c5(fn()=>$r2->step(static function(string $point): void {
        if($point==='before-ddl')throw new RuntimeException('Simulated PHP death before native SQL');
    }));
    checkF4c5($before,'Before-DDL exception simulated');
    checkF4c5(!$f2->inventory()['flag'], 'Before-DDL effect absent');
    checkF4c5($r2->status()['state']==='manual_recovery', 'Before-DDL inflight journal remains ambiguous');
    checkF4c5(throwsF4c5(fn()=>$r2->step()), 'Before-DDL automatic retry refused');
    checkF4c5((new UpdateDurableRecoveryGate($root2))->status()===UpdateDurableRecoveryGate::DATABASE, 'Before-DDL block survives');

    [$root3,$f3,$r3]=createF4c5();$fixtures[]=$root3;
    $r3->begin($hash,$hash,$hash);
    $after=throwsF4c5(fn()=>$r3->step(static function(string $point): void {
        if($point==='after-ddl')throw new RuntimeException('Simulated PHP death after native SQL');
    }));
    checkF4c5($after,'After-DDL exception simulated');
    checkF4c5($f3->inventory()['flag'], 'Committed synthetic DDL remains present');
    checkF4c5($r3->status()['state']==='manual_recovery', 'After-DDL journal still ambiguous');
    checkF4c5(throwsF4c5(fn()=>$r3->step()), 'After-DDL automatic repeat refused');
    checkF4c5((new UpdateDurableRecoveryGate($root3))->status()===UpdateDurableRecoveryGate::DATABASE, 'After-DDL block survives');

    [$root4,$f4,$r4]=createF4c5();$fixtures[]=$root4;
    $r4->begin($hash,$hash,$hash);
    $f4->failDdl=true;
    checkF4c5(throwsF4c5(fn()=>$r4->step()), 'Synthetic SQL exception propagated');
    checkF4c5($r4->status()['state']==='manual_recovery', 'SQL fault retains durable marker');
    checkF4c5(throwsF4c5(fn()=>$r4->step()), 'SQL fault cannot be auto-retried');
    $f4->failDdl=false;
    checkF4c5((new UpdateDurableRecoveryGate($root4))->status()===UpdateDurableRecoveryGate::DATABASE, 'SQL fault keeps recovery blocked');

    [$root5,$f5,$r5]=createF4c5();$fixtures[]=$root5;
    $r5->begin($hash,$hash,$hash);
    $path=glob($root5.'/storage/update-recovery/.db-update-pending-*')[0]??null;
    checkF4c5(is_string($path) && is_file($path), 'Corrupt test journal located');
    if (is_string($path)) file_put_contents($path, '{broken');
    checkF4c5($r5->status()['state']==='manual_recovery', 'Corrupt recovery journal fails closed');
    checkF4c5(throwsF4c5(fn()=>$r5->step()), 'Corrupt journal never executes DDL');
    checkF4c5((new UpdateDurableRecoveryGate($root5))->status()===UpdateDurableRecoveryGate::DATABASE, 'Corrupt marker still blocks F4c.2');

    $source=file_get_contents(dirname(__DIR__).'/core/classes/UpdateNativeMigrationRehearsal.php');
    checkF4c5(str_contains($source,'PHP_SAPI !== \'cli\''), 'Native QA only permits CLI');
    checkF4c5(str_contains($source,'--')===false || str_contains($source,'executeQaStep'), 'Explicit native QA target contract present');
    checkF4c5(!str_contains($source,'DbConf::') && !str_contains($source,'MigrationRunner::apply'), 'No active CMS DB or migrations invoked');
    checkF4c5(!str_contains($source,'public_html/admin/') && !str_contains($source, 'admin_verify_csrf'), 'No production HTTP routing');
    checkF4c5(!str_contains($source, 'DROP DATABASE') && !str_contains($source, 'DROP TABLE'), 'No destructive schema-drop SQL');
    checkF4c5(!str_contains($source, 'UPDATE schema_migrations') && !str_contains($source, 'INSERT INTO schema_migrations'), 'Production migration history untouched');
} catch(Throwable $e) {
    $fail++; fwrite(STDERR,'FAIL: Unexpected F4c.5 test error: '.get_class($e).' '.$e->getMessage()."\n");
} finally {
    foreach(array_reverse($fixtures) as $path) cleanF4c5($path);
}
echo "F4c.5 Native Migration QA1 synthetic: {$count} PASS / {$fail} FAIL (NO SQL; native separate)\n";
exit($fail===0?0:1);
