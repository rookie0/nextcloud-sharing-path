<?php
require __DIR__ . '/../lib/Http/ByteRange.php';
use OCA\SharingPath\Http\ByteRange;
$count = 0;
function check($ok, $label) {
    global $count;
    if (!$ok) throw new RuntimeException($label);
    $count++;
}
foreach ([
    ['bytes=1-3', 10, [['from'=>1,'to'=>3]]],
    ['bytes=-3', 10, [['from'=>7,'to'=>9]]],
    ['bytes=8-', 10, [['from'=>8,'to'=>9]]],
    ['bytes=8-999', 10, [['from'=>8,'to'=>9]]],
    ['bytes=-999999999999999999999', 10, [['from'=>0,'to'=>9]]],
    ['bytes=999999999999999999999-', 10, []],
    ['bytes=999-', 10, []], ['bytes=-0', 10, []], ['bytes=0-0', 0, []],
    ['bytes=0-1, 5-6', 10, [['from'=>0,'to'=>1],['from'=>5,'to'=>6]]],
    ['bytes=999-,1-2', 10, [['from'=>1,'to'=>2]]],
    ['bytes=1e2-200', 1000, null], ['bytes=foo', 10, null], ['items=1-2', 10, null],
    ['bytes='.implode(',',array_fill(0,17,'1-2')),10,null], ['',10,null], ['bytes=0-1',-1,null],
] as [$header, $size, $expected]) {
    check(ByteRange::parse($header,$size)===$expected, 'Range parser: '.$header);
}
$settings = json_decode(shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/settings.php')),true);
check($settings && $settings['passed']===12 && $settings['total']===12,'All settings authorization cases');
foreach ([['seek','',200,'abcdefghij','text/plain'],['seek','bytes=1-2',206,'bc','text/plain'],
    ['nonseek','bytes=1-2,5-6',200,'abcdefghij','text/plain'],['nonseek','bytes=1-2',200,'abcdefghij','text/plain'],
    ['seek','bytes=99-',416,'',null],['openfail','',404,"File not found or not publicly accessible.\n",'text/plain; charset=utf-8']] as [$mode,$range,$status,$body,$mime]) {
    $command=[PHP_BINARY,__DIR__.'/download.php',$mode,$range];
    $process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $actual=stream_get_contents($pipes[1]);$headers=json_decode(trim(stream_get_contents($pipes[2])),true);
    fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    check($exit===0 && $actual===$body && $headers && $headers['status']===$status,'Stream '.$mode.' '.$range.' response');
    if ($mime) check($headers['headers']['content-type']===$mime,'Stream '.$mode.' '.$range.' MIME');
    if ($mode==='nonseek') check(!isset($headers['headers']['content-range']) && !isset($headers['headers']['accept-ranges']),'Full fallback clears range headers');
}
echo "$count PHP checks passed (including all 12 settings authorization cases)\n";
