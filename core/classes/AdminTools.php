<?php
// Site Health can also be called from isolated tools without the public bootstrap.
require_once __DIR__ . '/../bootstrap/php-requirement.php';
/** Admin-only operational tools for the generic CMS base. */
final class AdminTools
{
    public const EXPORT_FORMAT = 'boilerplate-cms-export';
    public const EXPORT_VERSION = 1;
    public const MAX_IMPORT_BYTES = 10_485_760;

    private Db $db;
    private Settings $settings;

    private const TABLES = [
        'pages' => ['id','page_title','page_slug','parent_id','page_description','page_content','content_mode','page_status','menu_order','show_in_menu','created','updated','meta_title','meta_description'],
        'page_blocks' => ['id','page_id','block_type','block_data','sort_order','status','created_at','updated_at'],
        'redirects' => ['id','source_path','target_url','redirect_type','is_active','is_automatic','page_id','hit_count','last_hit_at','created_at','updated_at'],
        'settings' => ['id','setting_key','setting_value','autoload'],
    ];
    private const IMPORT_ORDER = ['pages','settings','redirects','page_blocks'];

    public function __construct(?Db $db = null)
    {
        $this->db = $db ?? new Db();
        $this->settings = new Settings();
    }

    public function siteHealth(): array
    {
        $checks = [];
        $add = static function(array &$target,string $status,string $label,string $detail): void { $target[]=['status'=>$status,'label'=>$label,'detail'=>$detail]; };
        $add($checks, boilerplate_php_version_supported(PHP_VERSION_ID) ? 'good' : 'error', 'PHP-version', PHP_VERSION . ' (minimum ' . BOILERPLATE_MIN_PHP_VERSION . ')');
        foreach (['mysqli'=>'MySQLi','fileinfo'=>'Fileinfo','openssl'=>'OpenSSL'] as $ext=>$label) {
            $add($checks, extension_loaded($ext)?'good':'error', $label, extension_loaded($ext)?'PHP-extension er aktiv.':'PHP-extension mangler.');
        }
        try {
            $pending = array_filter((new MigrationRunner($this->db))->status(),
                static fn(array $row): bool => ($row['state'] ?? '') !== 'applied');
            $add($checks, $pending ? 'warning' : 'good', 'Database migrations',
                $pending ? count($pending) . ' migration(s) require attention. Run the CLI migration tool.' : 'All packaged migrations are registered.');
        } catch (Throwable $e) {
            $add($checks, 'error', 'Database migrations', 'Migration status could not be verified.');
        }
        $backupSupport = class_exists(PharData::class) && extension_loaded('zlib');
        $add($checks, $backupSupport ? 'good' : 'warning', 'Site backup', $backupSupport ? 'Phar og zlib er tilgængelige.' : 'Site backup kræver PHP Phar og zlib.');
        $add($checks, extension_loaded('mbstring')?'good':'warning', 'Mbstring', extension_loaded('mbstring')?'PHP-extension er aktiv.':'Mbstring anbefales.');
        try { $ok=(int)$this->db->fetchValue('SELECT 1')===1; $add($checks,$ok?'good':'error','Database',$ok?'Forbindelsen til MySQL virker.':'Database-testen fejlede.'); }
        catch(Throwable $e){ $add($checks,'error','Database','Forbindelsen kunne ikke verificeres.'); }
        $uploadDir=PUBLICPATH.'/uploads';
        $add($checks,is_dir($uploadDir)&&is_writable($uploadDir)?'good':'error','Uploads',is_dir($uploadDir)&&is_writable($uploadDir)?'Upload-mappen er skrivbar.':'Upload-mappen mangler eller er ikke skrivbar.');
        $env=AppConfig::environment();
        $add($checks,$env==='production'?'good':'warning','Miljø','APP_ENV er sat til '.$env.'.');
        $configuredUrl = trim((string)$this->settings->get('site_url', ''));
        $databaseUrl = SiteUrl::normalizeOrigin($configuredUrl);
        $appUrl = AppConfig::appUrl();
        try {
            $selected = SiteUrl::resolve($this->settings);
            $add($checks, 'good', 'Hjemmeside URL', $selected['url'] . ' (kilde: ' . $selected['source'] . ').');
            if ($configuredUrl !== '' && $databaseUrl === null) {
                $add($checks, 'warning', 'Hjemmeside URL indstilling', 'Database-URL er ugyldig. APP_URL bruges om muligt som fallback.');
            }
            if ($databaseUrl !== null && $appUrl !== null && $databaseUrl !== $appUrl) {
                $add($checks, 'warning', 'URL-konfiguration', 'Hjemmeside URL og APP_URL er forskellige; databasens adresse anvendes.');
            }
            if (AppConfig::isProduction() && !str_starts_with($selected['url'], 'https://')) {
                $add($checks, 'error', 'HTTPS', 'Den effektive hjemmeside-URL skal bruge HTTPS i production.');
            }
        } catch (RuntimeException $error) {
            $add($checks, 'error', 'Hjemmeside URL', 'Ingen gyldig hjemmeside-URL eller APP_URL er konfigureret.');
        }
        $maintenance=(string)$this->settings->get('maintenance_mode','0')==='1';
        $indexing=(string)$this->settings->get('seo_indexing','1')==='1';
        $add($checks,$maintenance?'warning':'good','Vedligeholdelse',$maintenance?'Vedligeholdelsestilstand er aktiv.':'Vedligeholdelsestilstand er slået fra.');
        $add($checks,$indexing?'good':'warning','Søgemaskiner',$indexing?'Indeksering er tilladt.':'Global noindex er aktiv.');
        $counts=['good'=>0,'warning'=>0,'error'=>0];
        foreach($checks as $check) $counts[$check['status']]++;
        return ['checks'=>$checks,'counts'=>$counts,'overall'=>$counts['error']>0?'error':($counts['warning']>0?'warning':'good'),'checked_at'=>date('Y-m-d H:i:s')];
    }

    /** Downloadable read-only diagnostic report, excluding secret configuration and data rows. */
    public function siteHealthReport(): string
    {
        return SiteHealthReport::build($this->siteHealth(), $this->db);
    }

    public function export(string $profile): array
    {
        if ($profile !== 'content') {
            throw new InvalidArgumentException('Only partial JSON content export is supported here. Use Site Backup for full data.');
        }
        $data=[];$counts=[];
        foreach(array_keys(self::TABLES) as $table){ $rows=$this->db->fetchArray('SELECT * FROM `'.$table.'` ORDER BY `id` ASC')?:[]; $data[$table]=$rows; $counts[$table]=count($rows); }
        return ['format'=>self::EXPORT_FORMAT,'version'=>self::EXPORT_VERSION,'profile'=>$profile,'exported_at'=>gmdate('c'),'site_url'=>SiteUrl::origin($this->settings),'partial_export'=>true,'media_included'=>false,'authentication_included'=>false,'counts'=>$counts,'data'=>$data];
    }

    public function importUpload(array $file): array
    {
        if ((int)($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK) throw new RuntimeException('Vælg en gyldig eksportfil først.');
        $size=(int)($file['size']??0); if($size<1||$size>self::MAX_IMPORT_BYTES) throw new RuntimeException('Importfilen skal være mellem 1 byte og 10 MB.');
        $tmp=(string)($file['tmp_name']??''); if($tmp===''||!is_uploaded_file($tmp)) throw new RuntimeException('Uploaden kunne ikke valideres.');
        $raw=file_get_contents($tmp); if($raw===false) throw new RuntimeException('Importfilen kunne ikke læses.');
        $package=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($package)||(string)($package['format']??'')!==self::EXPORT_FORMAT||(int)($package['version']??0)!==self::EXPORT_VERSION) throw new RuntimeException('Filen er ikke en understøttet Boilerplate CMS-eksport.');
        $data=$package['data']??null; if(!is_array($data)) throw new RuntimeException('Eksportfilen mangler data.');
        foreach(array_keys($data) as $table) if(!isset(self::TABLES[$table])) throw new RuntimeException('Importfilen indeholder en ukendt tabel: '.$table.'.');
        $result=['tables'=>0,'rows'=>0,'counts'=>[]];
        $this->db->beginTransaction();
        try{
            foreach(self::IMPORT_ORDER as $table){
                if(!array_key_exists($table,$data)) continue;
                if(!is_array($data[$table])) throw new RuntimeException('Ugyldige data for '.$table.'.');
                $count=0;
                if($table==='pages'){
                    // Insert/update every page before restoring parent links. A page
                    // may have been re-parented under a newer (higher-id) page, so
                    // plain id-order import can otherwise violate the FK constraint.
                    $parents=[];
                    foreach($data[$table] as $row){
                        if(!is_array($row)) throw new RuntimeException('Ugyldig post i '.$table.'.');
                        $id=(int)($row['id']??0);
                        if($id<1) throw new RuntimeException('En post i pages mangler et gyldigt id.');
                        $parents[$id]=$row['parent_id']??null;
                        $withoutParent=$row;
                        $withoutParent['parent_id']=null;
                        $this->upsert($table,$withoutParent);
                        $count++;
                    }
                    foreach($parents as $id=>$parentId){
                        $parentId=($parentId===null||$parentId==='')?null:(int)$parentId;
                        if($parentId!==null){
                            if($parentId<1||$parentId===$id||(int)$this->db->fetchValue('SELECT COUNT(*) FROM pages WHERE id = ?',[$parentId])<1){
                                throw new RuntimeException('Ugyldig parent_id i pages-importen.');
                            }
                        }
                        $this->db->query('UPDATE pages SET parent_id = ? WHERE id = ?',[$parentId,$id]);
                    }
                } else {
                    foreach($data[$table] as $row){
                        if(!is_array($row)) throw new RuntimeException('Ugyldig post i '.$table.'.');
                        $this->upsert($table,$row);
                        $count++;
                    }
                }
                $result['tables']++;$result['rows']+=$count;$result['counts'][$table]=$count;
            }
            $this->normalizeOrdering(); $this->db->commit();
        }catch(Throwable $e){$this->db->rollback();throw $e;}
        return $result;
    }

    private function upsert(string $table,array $row): void
    {
        $allowed=self::TABLES[$table]??null; if($allowed===null) throw new RuntimeException('Ikke-tilladt importtabel.');
        $clean=[]; foreach($allowed as $column) if(array_key_exists($column,$row)) $clean[$column]=$row[$column];
        if($table==='settings'){
            $key=trim((string)($clean['setting_key']??'')); if($key==='') throw new RuntimeException('En indstilling mangler setting_key.');
            $existing=$this->db->fetchValue('SELECT id FROM settings WHERE setting_key = ? LIMIT 1',[$key]);
            if($existing!==null){$this->db->query('UPDATE settings SET setting_value = ?, autoload = ? WHERE setting_key = ?',[$clean['setting_value']??null,(int)($clean['autoload']??1),$key]);return;}
            $this->db->query('INSERT INTO settings (setting_key,setting_value,autoload) VALUES (?,?,?)',[$key,$clean['setting_value']??null,(int)($clean['autoload']??1)]);return;
        }
        $id=(int)($clean['id']??0); if($id<1) throw new RuntimeException('En post i '.$table.' mangler et gyldigt id.');
        $exists=(int)$this->db->fetchValue('SELECT COUNT(*) FROM `'.$table.'` WHERE id = ?',[$id])>0;
        if($exists){$updates=$clean;unset($updates['id']);if(!$updates)return;$set=implode(', ',array_map(static fn(string $c):string=>'`'.$c.'` = ?',array_keys($updates)));$params=array_values($updates);$params[]=$id;$this->db->query('UPDATE `'.$table.'` SET '.$set.' WHERE id = ?',$params);return;}
        $columns=array_keys($clean);$quoted=implode(', ',array_map(static fn(string $c):string=>'`'.$c.'`',$columns));$ph=implode(', ',array_fill(0,count($columns),'?'));$this->db->query('INSERT INTO `'.$table.'` ('.$quoted.') VALUES ('.$ph.')',array_values($clean));
    }

    private function normalizeOrdering(): void
    {
        $ordering=new AdminOrdering($this->db);
        foreach($this->db->fetchArray('SELECT DISTINCT parent_id FROM pages ORDER BY parent_id') as $parent) $ordering->normalize('pages',$parent['parent_id']===null?null:(int)$parent['parent_id']);
        foreach($this->db->fetchArray("SELECT id FROM pages WHERE content_mode = 'builder' ORDER BY id") as $page) $ordering->normalize('page_blocks',(int)$page['id']);
    }
}
