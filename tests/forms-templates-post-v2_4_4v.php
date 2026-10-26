<?php

declare(strict_types=1);
/* Run against real URL-encoded browser FormData exported by the Playwright harness. */
interface ModuleInterface {}
interface ModuleComponentProviderInterface {}
interface ModuleFrontendActionInterface {}
class Settings { public function get(string $key, mixed $default=null): mixed { return $default; } }
require __DIR__ . '/../modules/forms/FormsModule.php';
$module=(new ReflectionClass(FormsModule::class))->newInstanceWithoutConstructor();
$normalize=new ReflectionMethod(FormsModule::class,'normalizeAdminSubmission');
$base=__DIR__ . '/fixtures/forms_templates/';
$expected=['contact'=>[3,4], 'registration'=>[5,6], 'feedback'=>[3,4], 'inquiry'=>[4,5]];
$passed=0;
foreach($expected as $template=>$counts){
    parse_str((string)file_get_contents($base.'formdata-'.$template.'.txt'),$post);
    [$form,$fields,$errors,$rows]=$normalize->invoke($module,$post);
    if ($errors || count($rows)!==$counts[0] || count($fields)!==$counts[1]) {
        throw new RuntimeException('FAIL '.$template.' '.json_encode([$errors,count($rows),count($fields)]));
    }
    $keys=array_column($fields,'field_key');
    if(count(array_unique($keys))!==count($keys)) throw new RuntimeException('FAIL duplicate keys '.$template);
    if ($template==='contact' && ($rows[0]['layout']!=='50-50'||$fields[0]['label']!=='Fornavn'||$fields[1]['label']!=='Efternavn')) throw new RuntimeException('FAIL contact row');
    if ($template==='feedback' && !in_array('Meget tilfreds',array_merge(...array_map(fn($f)=>$f['options'],$fields)),true)) throw new RuntimeException('FAIL radio options');
    if ($template==='inquiry' && !in_array('Samarbejde',array_merge(...array_map(fn($f)=>$f['options'],$fields)),true)) throw new RuntimeException('FAIL select options');
    $passed++;
    echo 'PASS: Actual browser POST normalizes template '. $template. ' with '.$counts[0].' rows and '.$counts[1]." fields\n";
}
echo "Browser-to-PHP POST: {$passed} PASS / 0 FAIL\n";
