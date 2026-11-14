<?php

declare(strict_types=1);

/**
 * S5.2 production integration candidate: official release code + SQL cutover.
 * No admin endpoint; only the existing tools.manage + CSRF update controller
 * may call install(), holding UpdateMaintenanceGate EX throughout.
 * A separate database-INDEPENDENT controller authorizes recover().
 *
 * WARNING: arbitrary outside writers bypass the CMS maintenance flock. On a
 * host with independent writers, the maintainer must coordinate their pause.
 */
final class UpdateOneClickDatabaseCutover
{
    private string $root;
    private string $vault;

    public function __construct(string $root)
    {
        $real = realpath($root);
        if (!is_string($real) || !is_dir($real) || is_link($root))
            throw new RuntimeException('Unsafe DB update root.');
        $this->root = rtrim($real, '/');
        $this->vault = $this->root . '/storage/update-recovery';
    }

    /**
     * @return array{status:string,version:string}
     * The authenticated caller holds the exclusive UPDATE maintenance lease.
     * $recoverySecret is pre-issued in a prior HTTPS admin page response and
     * verified against the authenticated user's server session by the caller.
     */
    /**
     * Code-last forward-only cutover for EXACTLY the page_access migration.
     * Existing data is never restored from the forensic snapshot. All other
     * migration releases are denied, even if officially published.
     * Called only under authenticated Tools + exclusive maintenance gate.
     * @return array{status:string,version:string}
     */
    public function install(string $archive, string $version, string $sha, int $announced,
        string $recoverySecret): array
    {
        if ($version !== '2.5.1' || $announced !== 1
            || !preg_match('/\A[a-f0-9]{64}\z/D', $recoverySecret)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $sha)
            || !is_file($archive) || is_link($archive)
            || !hash_equals($sha, (string)hash_file('sha256', $archive))
            || (new UpdateDurableRecoveryGate($this->root))->status() !== UpdateDurableRecoveryGate::CLEAR) {
            return ['status'=>'unsupported_release','version'=>''];
        }
        $stage = null;
        $journalId = null;
        $zip = new ZipArchive();
        if ($zip->open($archive, ZipArchive::RDONLY) !== true) {
            return ['status'=>'unsupported_release','version'=>''];
        }
        try {
            $review = (new UpdatePackageReview($this->root))->inspect($archive, (int)filesize($archive));
            if (($review['status'] ?? null) !== 'review' || $review['target_version'] !== $version
                || $review['new_migrations'] !== 1) {
                throw new RuntimeException('Forward-only release review mismatch.');
            }
            $runner = new MigrationRunner(new Db(), $this->root);
            $states = $runner->status();
            foreach ($states as $row) {
                if (($row['state'] ?? null) !== 'applied') {
                    throw new RuntimeException('Existing migration history is unclean.');
                }
            }
            $migration = (new UpdateOneClickMigrationReview($this->root))
                ->inspect($archive, $sha, $states, 1);
            if (($migration['status'] ?? null) !== 'review_only'
                || ($migration['install_ready'] ?? null) !== false
                || ($migration['migrations'] ?? []) !== [UpdatePageAccessForwardOnlyPolicy::ID]) {
                throw new RuntimeException('Unapproved migration in official release.');
            }
            $scope = UpdateOneClickDatabaseReleaseScope::fromZip(
                $zip, [UpdatePageAccessForwardOnlyPolicy::ID]);
            if (($scope['tables'] ?? null) !== ['page_access'] || ($scope['triggers'] ?? null) !== []) {
                throw new RuntimeException('Unexpected migration object declaration.');
            }
            $newMigration = $zip->locateName('boilerplate/' . UpdatePageAccessForwardOnlyPolicy::PATH);
            if ($newMigration === false || $zip->getFromIndex($newMigration) === false
                || !hash_equals(UpdatePageAccessForwardOnlyPolicy::SOURCE_SHA256,
                    hash('sha256', (string)$zip->getFromIndex($newMigration)))) {
                throw new RuntimeException('Unknown executable migration source.');
            }
            $connection = self::connect();
            try {
                UpdatePageAccessForwardOnlyPolicy::assertFreshTarget($connection);
                if ($connection->query("SELECT COUNT(*) FROM schema_migrations "
                    . "WHERE migration_id='core:20261105_000_page_access'")->fetch_row()[0] != 0) {
                    throw new RuntimeException('Migration registry already claims page_access.');
                }
            } finally { $connection->close(); }

            // Forensic backup is validated but NEVER fed to a database restore.
            $backup = (new UpdateRecoveryBundle($this->root))->create();
            (new UpdateRecoveryBundle($this->root))->verify($backup['id']);
            $archiveBackup = $this->vault . '/' . $backup['id'] . '/data.tar.gz';
            $backupSha = hash_file('sha256', $archiveBackup);
            if (!is_string($backupSha)) throw new RuntimeException('Cannot seal forensic backup.');

            $stage = $this->vault . '/.db-stage-' . bin2hex(random_bytes(16));
            if (!mkdir($stage, 0700) || !mkdir($stage.'/old', 0700)
                || !mkdir($stage.'/new', 0700)) throw new RuntimeException('Cannot prepare private code stage.');
            $plan = $this->stageFiles($zip, $stage);
            if ($plan === []) throw new RuntimeException('No update file changes.');
            UpdatePageAccessForwardOnlyPolicy::inspectSource($stage.'/new/'.UpdatePageAccessForwardOnlyPolicy::PATH);
            $meta = [
                'format'=>'boilerplate-db-file-plan-v1',
                'mode'=>'forward-only-page-access-v1',
                'version'=>$version, 'archive_sha256'=>$sha,
                'backup_id'=>$backup['id'], 'plan'=>$plan, 'declared'=>$scope,
            ];
            $encoded = json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            if (!is_string($encoded) || strlen($encoded)>524288) throw new RuntimeException('Oversized code plan.');
            self::restrictedWrite($stage.'/manifest.json', $encoded);
            $journal = new UpdateOneClickDatabaseJournal($this->root);
            // Marker is durable BEFORE the first irreversible DDL.
            $ticket = $journal->begin(DbConf::name(), $backup['id'], $backupSha,
                $sha, hash('sha256',$encoded), [UpdatePageAccessForwardOnlyPolicy::ID], $recoverySecret);
            $journalId = $ticket['id'];
            $work = $this->work($journalId);
            if (!rename($stage, $work)) throw new RuntimeException('Cannot seal private code stage.');
            $stage = null;
            // 'files_written' is the legacy durable checkpoint name. In this
            // forward-only mode it means "code staged", NOT installed.
            $journal->checkpoint($journalId, 'files_written');
            $journal->checkpoint($journalId, 'database_running');
            $connection = self::connect();
            try {
                UpdatePageAccessForwardOnlyPolicy::assertFreshTarget($connection);
                $file = $work.'/new/'.UpdatePageAccessForwardOnlyPolicy::PATH;
                UpdatePageAccessForwardOnlyPolicy::inspectSource($file);
                $callable = require $file;
                if (!is_callable($callable)) throw new RuntimeException('Invalid pinned migration callable.');
                $callable(new Db());
                UpdatePageAccessForwardOnlyPolicy::assertSchema($connection);
                UpdatePageAccessForwardOnlyPolicy::assertMigrationStamp(new Db(), true);
            } finally { $connection->close(); }
            $journal->checkpoint($journalId, 'database_written');
            // Code-last: existing application source remains intact until DDL
            // and its migration history have both passed postflight.
            $this->applyFiles($work, $plan);
            $this->assertForwardPostflight($plan, $version);
            $journal->checkpoint($journalId, 'postflight_verified');
            $this->archiveJournal($journalId, 'committed');
            try { $this->cleanup($work); } catch (Throwable $ignored) {
                error_log('Forward-only completed code cleanup pending.');
            }
            try { (new UpdateRecoveryBundle($this->root))->pruneAfterSuccessfulInstall($backup['id']); }
            catch (Throwable $ignored) { error_log('Forensic backup retention pending.'); }
            return ['status'=>'updated','version'=>$version];
        } catch (Throwable $e) {
            error_log('Forward-only update stopped: '.get_class($e));
            if ($journalId !== null) {
                if ($stage !== null && is_dir($stage)) {
                    try {
                        if (rename($stage, $this->work($journalId))) $stage=null;
                    } catch (Throwable $ignored) { /* Journal remains blocked. */ }
                }
                try {
                    $journal = new UpdateOneClickDatabaseJournal($this->root);
                    if ($journal->inspect($journalId)['phase'] !== 'recovery_required') {
                        $journal->checkpoint($journalId, 'recovery_required');
                    }
                } catch (Throwable $ignored) { /* Journal still blocks HTTP. */ }
                return ['status'=>'recovery_required','version'=>''];
            }
            return ['status'=>'unsupported_release','version'=>''];
        } finally {
            $zip->close();
            if ($stage !== null && is_dir($stage)) {
                try { $this->cleanup($stage); } catch (Throwable $ignored) { /* Retain unsafe stage. */ }
            }
        }
    }

    /**
     * Authorized non-destructive forward-only reconciliation. Never deletes
     * database tables, restores an old SQL snapshot or replays arbitrary PHP.
     * This is reachable only through the existing HTTPS capability + CSRF
     * controller holding the exclusive update gate.
     * @return array{status:string}
     */
    public function recover(string $id): array
    {
        $journal = new UpdateOneClickDatabaseJournal($this->root);
        $state = $journal->inspect($id);
        if (($state['schema'] ?? null) !== DbConf::name()
            || ($state['migration_ids'] ?? null) !== [UpdatePageAccessForwardOnlyPolicy::ID]) {
            throw new RuntimeException('Recovery is not an approved forward-only migration.');
        }
        $work = $this->work($id);
        $meta = $this->loadPlan($work, $state);
        if (($meta['mode'] ?? null) !== 'forward-only-page-access-v1'
            || ($meta['version'] ?? null) !== '2.5.1'
            || ($meta['declared']['tables'] ?? null) !== ['page_access']
            || ($meta['declared']['triggers'] ?? null) !== []
            || ($meta['archive_sha256'] ?? null) !== $state['release_sha256']) {
            throw new RuntimeException('Unknown journal mode; SQL restore disabled.');
        }
        $migrationFile = $work.'/new/'.UpdatePageAccessForwardOnlyPolicy::PATH;
        UpdatePageAccessForwardOnlyPolicy::inspectSource($migrationFile);
        $plan = $meta['plan'];
        // Do not allow completion from any unrecognized source or file plan.
        $found = false;
        foreach ($plan as $entry) {
            if ($entry['path'] === UpdatePageAccessForwardOnlyPolicy::PATH
                && $entry['new'] === UpdatePageAccessForwardOnlyPolicy::SOURCE_SHA256) $found = true;
        }
        if (!$found) throw new RuntimeException('Pinned migration absent from code plan.');
        $connection = self::connect();
        try {
            $exists = UpdatePageAccessForwardOnlyPolicy::exists($connection);
            if ($exists) {
                UpdatePageAccessForwardOnlyPolicy::assertSchema($connection);
            } else {
                $db = new Db();
                $record = $db->fetchArray('SELECT checksum_sha256 FROM schema_migrations WHERE migration_id = ?',
                    [UpdatePageAccessForwardOnlyPolicy::ID]);
                if ($record !== []) throw new RuntimeException('Migration stamped but new table absent.');
            }
        } finally { $connection->close(); }
        if (!in_array($state['phase'], ['recovery_required','recovering','restored'], true)) {
            $journal->checkpoint($id, 'recovery_required');
        }
        if ($state['phase'] !== 'recovering' && $state['phase'] !== 'restored') {
            $journal->checkpoint($id, 'recovering');
        }
        if (!$exists) {
            // No DDL took effect: restore ONLY code if it was partly changed.
            $this->restoreFiles($work, $plan);
            if ($journal->inspect($id)['phase'] !== 'restored') $journal->checkpoint($id, 'restored');
            $this->archiveJournal($id, 'restored');
        } else {
            // DDL already committed: finish forward without ever restoring SQL.
            UpdatePageAccessForwardOnlyPolicy::assertMigrationStamp(new Db(), true);
            $this->resumeForwardFiles($work, $plan);
            $this->assertForwardPostflight($plan, '2.5.1');
            if ($journal->inspect($id)['phase'] !== 'restored') $journal->checkpoint($id, 'restored');
            $this->archiveJournal($id, 'committed');
        }
        try { $this->cleanup($work); }
        catch (Throwable $ignored) { error_log('Forward-only resumed code cleanup pending.'); }
        return ['status'=>'restored'];
    }

    /** Idempotent code-only resume: old OR new hashes, never an unknown third-party file. */
    private function resumeForwardFiles(string $work, array $plan): void
    {
        foreach ($plan as $file) {
            $target = $this->destination($file['path']);
            $current = is_file($target) ? hash_file('sha256', $target) : null;
            if ($current === $file['new']) continue;
            if ($current !== $file['old']) {
                throw new RuntimeException('Externally changed code requires manual review.');
            }
            if ($file['new'] === null) {
                if (is_link($target) || !unlink($target)) throw new RuntimeException('Cannot retire owned code.');
                UpdateRuntimeCacheGuard::afterMutation($target);
            } else {
                $this->replace($work.'/new/'.$file['path'], $target, $file['new']);
            }
        }
        $this->verifyNewFiles($plan);
    }

    private function assertForwardPostflight(array $plan, string $expectedVersion): void
    {
        $this->verifyNewFiles($plan);
        $init = @file_get_contents($this->root.'/public_html/incl/init.php');
        if (!is_string($init) || !preg_match(UpdateReleaseVersion::SOURCE_PATTERN, $init, $m)
            || $m[1] !== $expectedVersion) {
            throw new RuntimeException('Forward-only version verification failed.');
        }
        $connection = self::connect();
        try { UpdatePageAccessForwardOnlyPolicy::assertSchema($connection); }
        finally { $connection->close(); }
        UpdatePageAccessForwardOnlyPolicy::assertMigrationStamp(new Db(), false);
        foreach ((new MigrationRunner(new Db(), $this->root))->status() as $row) {
            if (($row['state'] ?? null) !== 'applied') {
                throw new RuntimeException('Forward-only migration postflight mismatch.');
            }
        }
    }

    /** @return array{format:string,version:string,archive_sha256:string,backup_id:string,plan:list<array>,declared:array} */
    private function loadPlan(string $work, array $state): array
    {
        if (is_link($work) || !is_dir($work) || (fileperms($work) & 0077) !== 0
            || is_link($work.'/manifest.json') || !is_file($work.'/manifest.json'))
            throw new RuntimeException('Missing protected DB file plan.');
        $bytes = file_get_contents($work.'/manifest.json');
        if (!is_string($bytes) || strlen($bytes) > 524288 || !hash_equals($state['file_plan_sha256'],hash('sha256',$bytes)))
            throw new RuntimeException('Modified DB update plan.');
        $meta = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($meta) || ($meta['format'] ?? '') !== 'boilerplate-db-file-plan-v1'
            || !is_array($meta['plan'] ?? null) || count($meta['plan']) > 1500
            || !is_array($meta['declared'] ?? null) || array_keys($meta['declared']) !== ['tables','triggers'])
            throw new RuntimeException('Invalid DB file plan format.');
        foreach (['tables','triggers'] as $type) {
            if (!array_is_list($meta['declared'][$type])) throw new RuntimeException('Bad DB scope list.');
            foreach ($meta['declared'][$type] as $name) {
                if (!is_string($name) || !preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D',$name))
                    throw new RuntimeException('Unsafe DB scope name.');
            }
        }
        $this->validatePlan($meta['plan']);
        return $meta;
    }

    /** @param list<array> $plan */
    private function validatePlan(array $plan): void
    {
        $seen=[];
        foreach ($plan as $entry) {
            if (!is_array($entry) || !is_string($entry['path'] ?? null)
                || !array_key_exists('old',$entry) || !array_key_exists('new',$entry)
                || !UpdatePackageReview::isSafeMember('boilerplate/'.$entry['path'],false))
                throw new RuntimeException('Untrusted update file path.');
            foreach (['old','new'] as $key) {
                if (($entry[$key] !== null) && (!is_string($entry[$key])
                    || preg_match('/\A[a-f0-9]{64}\z/D',$entry[$key]) !== 1))
                    throw new RuntimeException('Invalid update file digest.');
            }
            if ($entry['old'] === null && $entry['new'] === null)
                throw new RuntimeException('Empty file update plan entry.');
            if ($entry['new'] === null && !UpdateRetiredFilePlan::isOwnedCodePath($entry['path']))
                throw new RuntimeException('Unsafe file retirement.');
            $key=strtolower($entry['path']);
            if (isset($seen[$key])) throw new RuntimeException('Duplicate update file path.');
            $seen[$key]=true;
        }
        if (count($plan)>1500) throw new RuntimeException('File plan exceeds bound.');
    }

    /** @return list<array{path:string,old:?string,new:?string}> */
    private function stageFiles(ZipArchive $zip, string $work): array
    {
        $archiveFiles=[]; $seen=[]; $plan=[];
        for($i=0;$i<$zip->numFiles;$i++) {
            $stat=$zip->statIndex($i);
            if(!is_array($stat)||!is_string($stat['name']??null)) throw new RuntimeException('Invalid ZIP file.');
            $member=$stat['name']; $dir=str_ends_with($member,'/');
            if(!UpdatePackageReview::isSafeMember($member,$dir)) throw new RuntimeException('Unsafe release member.');
            if($dir) continue;
            $rel=substr($member,strlen('boilerplate/'));
            $key=strtolower($rel);
            if(isset($seen[$key])) throw new RuntimeException('Duplicate release member.');
            $seen[$key]=true; $archiveFiles[$rel]=$i;
            $dest=$this->destination($rel);
            if ($rel === 'public_html/.user.ini' && (!is_file($dest)
                || !hash_equals((string)hash_file('sha256',$dest),
                   hash('sha256',(string)$zip->getFromIndex($i)))))
                throw new RuntimeException('Hosting PHP settings require manual review.');
            $payload=$zip->getFromIndex($i);
            if(!is_string($payload)||strlen($payload)!==(int)$stat['size'])
                throw new RuntimeException('Malformed release member.');
            $old=is_file($dest)?hash_file('sha256',$dest):null;
            if($old!==null && !is_string($old)) throw new RuntimeException('Cannot hash installed file.');
            $new=hash('sha256',$payload);
            if($old === $new) continue;
            $newFile=$work.'/new/'.$rel;
            self::restrictedWrite($newFile,$payload);
            if($old!==null){
                $oldFile=$work.'/old/'.$rel;
                $this->copyOld($dest,$oldFile,$old);
            }
            $plan[]=['path'=>$rel,'old'=>$old,'new'=>$new];
        }
        foreach(UpdateRetiredFilePlan::fromZip($zip,$archiveFiles) as $rel=>$old){
            $dest=$this->destination($rel);
            if(!is_file($dest)) continue;
            if(!hash_equals($old,(string)hash_file('sha256',$dest)))
                throw new RuntimeException('Retired code checksum mismatch.');
            $this->copyOld($dest,$work.'/old/'.$rel,$old);
            $plan[]=['path'=>$rel,'old'=>$old,'new'=>null];
        }
        $this->validatePlan($plan);
        return $plan;
    }

    /** @param list<array> $plan */
    private function applyFiles(string $work, array $plan): void
    {
        foreach($plan as $file){
            $target=$this->destination($file['path']);
            $current=is_file($target)?hash_file('sha256',$target):null;
            if($current!==$file['old']) throw new RuntimeException('Installed file changed during cutover.');
            if($file['new']===null){
                if(is_link($target)||!unlink($target)) throw new RuntimeException('Cannot retire changed file.');
                UpdateRuntimeCacheGuard::afterMutation($target);
            } else {
                $this->replace($work.'/new/'.$file['path'],$target,$file['new']);
            }
        }
        $this->verifyNewFiles($plan);
    }

    /** @param list<array> $plan */
    private function verifyNewFiles(array $plan): void
    {
        foreach($plan as $file){
            $target=$this->destination($file['path']);
            $actual=is_file($target)?hash_file('sha256',$target):null;
            if($actual!==$file['new']) throw new RuntimeException('Installed file postflight failed.');
        }
    }

    /** @param list<array> $plan */
    private function restoreFiles(string $work, array $plan): void
    {
        foreach(array_reverse($plan) as $file){
            $target=$this->destination($file['path']);
            $actual=is_file($target)?hash_file('sha256',$target):null;
            if($actual===$file['old']) continue;
            if($actual!==$file['new']) throw new RuntimeException('Unexpected third-party code change; manual recovery needed.');
            if($file['old']===null){
                if(is_link($target)||!unlink($target)) throw new RuntimeException('Cannot remove newly installed file.');
                UpdateRuntimeCacheGuard::afterMutation($target);
            } else $this->replace($work.'/old/'.$file['path'],$target,$file['old']);
        }
        $this->verifyOldFiles($plan);
    }

    /** @param list<array> $plan */
    private function verifyOldFiles(array $plan): void
    {
        foreach($plan as $file){
            $target=$this->destination($file['path']);
            $actual=is_file($target)?hash_file('sha256',$target):null;
            if($actual!==$file['old']) throw new RuntimeException('File rollback verification failed.');
        }
    }

    private function verifyRestoredDatabase(mysqli $db, string $backupPath, array $baseline): void
    {
        $live=UpdateOneClickDatabaseRecoveryScope::inspectMySQL($db,DbConf::name());
        if($live!==$baseline) throw new RuntimeException('Restored schema inventory differs from backup.');
        $tar=new PharData(realpath($backupPath));
        $manifest=json_decode($tar['manifest.json']->getContent(),true,32,JSON_THROW_ON_ERROR);
        $map=$manifest['database_tables']??null;
        if(!is_array($map)||count($map)!==count($baseline['tables']))
            throw new RuntimeException('Restored table inventory missing.');
        foreach($map as $table=>$count){
            if(!is_string($table)||!preg_match('/\A[A-Za-z_][A-Za-z0-9_]{0,63}\z/D',$table)
                || !is_int($count)||$count<0) throw new RuntimeException('Invalid backup row count.');
            $rows=$db->query('SELECT COUNT(*) FROM `'.$table.'`');
            if(!$rows instanceof mysqli_result) throw new RuntimeException('Cannot inspect restored rows.');
            try{$found=(int)($rows->fetch_row()[0]??-1);} finally{$rows->free();}
            if($found!==$count) throw new RuntimeException('Restored table row mismatch.');
        }
    }

    /** The SQL/media backup must still correspond to the restored media tree. */
    private function verifyManagedMedia(string $backupPath): void
    {
        $archive = realpath($backupPath);
        if (!is_string($archive)) throw new RuntimeException('Cannot resolve managed media backup.');
        $tar = new PharData($archive);
        $manifest = json_decode($tar['manifest.json']->getContent(), true, 32, JSON_THROW_ON_ERROR);
        $files = $manifest['upload_sha256'] ?? null;
        if (!is_array($files) || count($files) > 100000) {
            throw new RuntimeException('Invalid media recovery inventory.');
        }
        foreach ($files as $name => $digest) {
            if (!is_string($name) || !preg_match('~\Apublic_html/(?:uploads|assets/uploads)/[A-Za-z0-9._/-]+\z/D',$name)
                || str_contains('/'.$name.'/', '/../') || str_contains('/'.$name.'/', '/./')
                || !is_string($digest) || !preg_match('/\A[a-f0-9]{64}\z/D',$digest)) {
                throw new RuntimeException('Unsafe managed media entry.');
            }
            $path = $this->root;
            foreach (explode('/', $name) as $part) {
                $path .= '/' . $part;
                if (is_link($path)) throw new RuntimeException('Symlink in managed media location.');
            }
            if (!is_file($path) || !hash_equals($digest,(string)hash_file('sha256',$path))) {
                throw new RuntimeException('Managed media changed since recovery point.');
            }
        }
    }

    private function destination(string $relative): string
    {
        if(!UpdatePackageReview::isSafeMember('boilerplate/'.$relative,false))
            throw new RuntimeException('Unsafe destination path.');
        $path=$this->root; $parts=explode('/',$relative);
        foreach($parts as $i=>$part){
            $path.='/'.$part;
            if(is_link($path)) throw new RuntimeException('Symlink in application path.');
            if($i<count($parts)-1 && file_exists($path)&&!is_dir($path))
                throw new RuntimeException('Unexpected directory type.');
        }
        if(file_exists($path)&&!is_file($path)) throw new RuntimeException('Unexpected destination type.');
        return $path;
    }

    private function copyOld(string $source,string $target,string $expected): void
    {
        $parent=dirname($target);
        if(!is_dir($parent)&&!mkdir($parent,0700,true)) throw new RuntimeException('Cannot create backup directory.');
        if(is_link($source)||is_link($target)||file_exists($target)||!copy($source,$target))
            throw new RuntimeException('Cannot stage old source.');
        @chmod($target,0600);
        if(!hash_equals($expected,(string)hash_file('sha256',$target)))
            throw new RuntimeException('Staged old code changed.');
    }

    private function replace(string $source,string $target,string $hash): void
    {
        if(is_link($source)||!is_file($source)||!hash_equals($hash,(string)hash_file('sha256',$source)))
            throw new RuntimeException('Invalid source for code replacement.');
        $parent=dirname($target);
        if(!is_dir($parent)&&!mkdir($parent,0755,true)) throw new RuntimeException('Cannot create code directory.');
        $this->destination(substr($target,strlen($this->root)+1));
        $temp=tempnam($parent,'.bp-code-');
        if(!is_string($temp)) throw new RuntimeException('Cannot stage atomic update.');
        try {
            if(!copy($source,$temp)||!hash_equals($hash,(string)hash_file('sha256',$temp)))
                throw new RuntimeException('Temporary code payload mismatch.');
            @chmod($temp,0644);
            if(!rename($temp,$target)) throw new RuntimeException('Cannot install updated code.');
            UpdateRuntimeCacheGuard::afterMutation($target);
        } finally { if(is_file($temp)) @unlink($temp); }
    }

    private function work(string $id): string
    {
        if(!preg_match('/\A[a-f0-9]{32}\z/D',$id)) throw new RuntimeException('Invalid DB work identity.');
        return $this->vault.'/.db-work-'.$id;
    }

    private static function restrictedWrite(string $path,string $data): void
    {
        $dir=dirname($path);
        if(!is_dir($dir)&&!mkdir($dir,0700,true)) throw new RuntimeException('Cannot create private staging path.');
        if(file_exists($path)||is_link($path)) throw new RuntimeException('Cannot overwrite private stage.');
        $handle=@fopen($path,'xb');
        if(!is_resource($handle)) throw new RuntimeException('Cannot create private staged file.');
        try {
            @chmod($path,0600);
            if(fwrite($handle,$data)!==strlen($data)||!fflush($handle)||!function_exists('fsync')||!fsync($handle))
                throw new RuntimeException('Cannot durably stage private source.');
        } finally { fclose($handle); }
    }

    private static function connect(): mysqli
    {
        $db=new mysqli(DbConf::host(),DbConf::user(),DbConf::pass(),DbConf::name(),DbConf::port());
        if(!$db->set_charset('utf8mb4')) throw new RuntimeException('Cannot set database charset.');
        return $db;
    }

    private static function assertTriggerRestoreCompatible(mysqli $db,int $count): void
    {
        if($count===0) return;
        $binlog=(string)$db->query('SELECT @@GLOBAL.log_bin')->fetch_row()[0];
        $trust=(string)$db->query('SELECT @@GLOBAL.log_bin_trust_function_creators')->fetch_row()[0];
        if(in_array(strtolower($binlog),['on','1'],true)&&!in_array(strtolower($trust),['on','1'],true))
            throw new RuntimeException('Host cannot restore triggers with current binary log settings.');
    }

    private function archiveJournal(string $id,string $status): void
    {
        if(!in_array($status,['committed','restored'],true)||!preg_match('/\A[a-f0-9]{32}\z/D',$id))
            throw new RuntimeException('Invalid completion marker.');
        $from=$this->vault.'/.db-update-pending-'.$id;
        $to=$this->vault.'/.complete-db-update-'.$status.'-'.$id;
        if(is_link($from)||!is_file($from)||file_exists($to)||is_link($to)||!rename($from,$to))
            throw new RuntimeException('Cannot finalize the durable database journal.');
    }

    private function cleanup(string $dir): void
    {
        if(is_link($dir)||!is_dir($dir)||dirname($dir)!==$this->vault
            || !preg_match('/\A\.db-(?:stage|work)-[a-f0-9]{32}\z/D',basename($dir)))
            throw new RuntimeException('Unsafe DB update cleanup path.');
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $f){
            if($f->isLink())throw new RuntimeException('Unsafe linked update artifact.');
            if($f->isDir()) { if(!rmdir($f->getPathname()))throw new RuntimeException('Cannot clean stage directory.'); }
            elseif($f->isFile()) { if(!unlink($f->getPathname()))throw new RuntimeException('Cannot clean staged file.'); }
            else throw new RuntimeException('Unexpected stage type.');
        }
        if(!rmdir($dir))throw new RuntimeException('Cannot remove private update stage.');
    }
}
