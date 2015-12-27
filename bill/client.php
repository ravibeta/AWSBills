<?php
require 'vendor/autoload.php';
use Aws\CloudWatch\CloudWatchClient;

$key = "AKIAJE2BD5HC65LYSODQ";
$secret = "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";
$region = "us-west-2";
$version = "latest";
$interval = 15;

/*
$client = CloudWatchClient::factory(array(
  'key'    => $key,
  'secret' => $secret,
  'region' => $region,
  'version' => $version,
));
*/
// Users/rajamani/Downloads/devserver/bill/vendor/aws/aws-sdk-php/src

// Use the us-west-2 region and latest version of each client.
$sharedConfig = [
    'region'  => $region,
    'version' => $version,
  'credentials' => array(
    'key' => $key,
    'secret'  => $secret,
  ),
    'key'    => $key,
    'secret' => $secret,
];

// Create an SDK class used to share configuration across clients.
$sdk = new Aws\Sdk($sharedConfig);
$client = $sdk->createCloudWatch();

function grabber($client, $tablename, $metric) {
  $output = array();
  date_default_timezone_set();
  $results = $client->getMetricStatistics(array(
    'Namespace'  => 'AWS/ECS',
    'MetricName' => $metric,
    'Dimensions' => array( 
      array(
        'Name' => 'TableName',
        'Value' => $tablename,
      ),
    ),
    'StartTime'  => strtotime('-1 days'), //'-'.$interval.' minutes'),
    'EndTime'    => strtotime('now'),
    'Period'     => 300,
    'Statistics' => array('Minimum', 'Maximum', 'Average', 'Sum'),
  ));
  echo 'RESULTS='.serialize($results);

  print "-------------------------------------------\n";
  print "    $metric\n";
  print "-------------------------------------------\n";
  foreach ($results as $result){
  echo 'RESULT='.serialize($result);
  if (is_array($result) && array_key_exists('Datapoints', $result)){
  foreach ( $result['Datapoints'] as $item ) {
    $min = $item['Minimum'];
    $max = $item['Maximum'];
    $avg = $item['Average'];
    $sum = $item['Sum'];
    $time = $item['Timestamp'];
    print "$time -- min $min, max $max, avg $avg, sum $sum\n";
    array_push($output, array('key'=>$time, 'min'=>$min, 'max'=>$max, 'avg'=>$avg, 'sum'=>$sum, 'cumulate'=>true, 'units'=>'count'));
    echo 'OUTPUT='.serialize($output);
  }
  }
  }
  return $output;
}

function grab_stats($client, $tablename) {
  print "\n==========================================================\n";
  print "    $tablename\n";
  print "==========================================================\n";

  $cpuOutput = grabber($client, $tablename, 'CPUCreditBalance'); // per instance
  $volOutput = grabber($client, $tablename, 'VolumeWriteOps'); // per volume
  return array('cpu'=>$cpuOutput, 'vol'=>$volOutput);
}
function addCurrentToCumulative($current, $cumulative){
if (!is_array($cumulative)) return array();
  foreach ( $current as $metric ){

      if (is_array($cumulative) && array_key_exists($metric['key'], $cumulative) == false){
           $cumulative[$metric['key']] = array('key'=>$metric[key], 'value' => 0, 'units' => $metric['count']);
      }

      if (is_array($cumulative) && array_key_exists('cumulate', $metric) && $metric['cumulate']){

          if (array_key_exists($metric['key'], $cumulative)){
             $cumulative[$metric['key']]['value'] += $metric['sum'];
          }else{
             $cumulative[$metric['key']]['value'] = $metric['sum'];
          }

      }else{
           $cumulative[$metric['key']]['value'] = $metric['avg'];
      }
  }
  return $cumulative;
}
function saveOrPrintCumulative($cumulative){
  print "\n==========================================================\n";
  print "    Aggregated Metrics\n";
  print "==========================================================\n";
  echo 'CUMULATIVE='.serialize($cumulative);  
  foreach($cumulative as $item){
    $key = $item['key'];
    $value = $item['value'];
    $units = $item['units'];
    print "Metric -- $key $value $units \n";
  }

}

// after every $interval minutes
// cumulate by adding current to total
// update current with grab_stats

$tablename = "Metrics";
$current = array();
$cumulative  = addCurrentToCumulative($current, $cumulative);
$current = grab_stats($client, $tablename);
//Sleep($interval*60); // seconds
$cumulative  = addCurrentToCumulative($current, $cumulative);
saveOrPrintCumulative($cumulative);
//$current = grab_stats($client, $tablename);
?>
