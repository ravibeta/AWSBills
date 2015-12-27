<?php
require 'vendor/autoload.php';
use Aws\CloudWatch\CloudWatchClient;

$key = "AKIAJE2BD5HC65LYSODQ";
$secret = "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";
$region = "us-west-2";
$version = "latest";
$interval = 15;

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
  date_default_timezone_set('America/Los_Angeles');
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
  //echo 'GRABBER='.serialize($results);

  //print "-------------------------------------------\n";
  //print "    $metric\n";
  //print "-------------------------------------------\n";
$output = array('key'=>$metric, 'min'=>1, 'max'=>5, 'avg'=>2, 'sum'=>2, 'cumulate'=>true, 'units'=>'count');
/*
  foreach ($results as $result){
  //echo 'RESULT='.serialize($result);
  if (is_array($result) && array_key_exists('Datapoints', $result)){
  foreach ( $result['Datapoints'] as $item ) {
    $min = $item['Minimum'];
    $max = $item['Maximum'];
    $avg = $item['Average'];
    $sum = $item['Sum'];
    $time = $item['Timestamp'];
    //print "$metric -- min $min, max $max, avg $avg, sum $sum\n";
    array_push($output, array('key'=>$metric, 'min'=>$min, 'max'=>$max, 'avg'=>$avg, 'sum'=>$sum, 'cumulate'=>true, 'units'=>'count'));
    // echo 'OUTPUT='.serialize($output);
  }
  }
  }
*/
  //echo 'GRABBER='.serialize($output);
  return $output;
}

function grab_stats($client, $tablename) {
  //print "\n==========================================================\n";
  //print "    $tablename\n";
  //print "==========================================================\n";

  $output = array();
$result = $client->listMetrics(array(
));
$result = (array)$result;
foreach ($result as $item){
   foreach ($item as $metrics){
     foreach ($metrics as $metric){
        //echo 'METRIC='.serialize($metric);
         if (is_array($metric) && 
             array_key_exists('MetricName', $metric) &&
             array_key_exists('Dimensions', $metric)){
          //echo $metric['MetricName'] . ' - '
          //     . $metric['Dimensions'][0]['Name'] . ' - '
          //     . $metric['Dimensions'][0]['Value'] . "\n";
          $output[$metric['MetricName']] = grabber($client, $tablename, $metric['MetricName']);
          }
     }
   }
}
//echo 'grab_STATS='.serialize($output);
  return $output;
  //$cpuOutput = grabber($client, $tablename, 'CPUCreditBalance'); // per instance
  //$volOutput = grabber($client, $tablename, 'VolumeWriteOps'); // per volume
  //return array('cpu'=>$cpuOutput, 'vol'=>$volOutput);
}
function addCurrentToCumulative($current, $cumulative){
  if (!is_array($cumulative)) return array();
  //echo 'CURRENT='.serialize($current);
  // echo 'CUMULATIVE='.serialize($cumulative)."\n";
  foreach ( $current as $metric ){
      //echo 'CUMMETRIC='.serialize($metric)."\n";
      if (is_array($cumulative) && array_key_exists($metric['key'], $cumulative) == false){
           $cumulative[$metric['key']] = array('key'=>$metric[key], 'value' => 0, 'units' => $metric['count']);
      }
      if (is_array($cumulative) && array_key_exists('cumulate', $metric) && $metric['cumulate']){

          if (array_key_exists($metric['key'], $cumulative)){
             //echo 'adding '.$metric['key'].' values old:'.$cumulative[$metric['key']]['value'].' and new:'.$metric['sum'];
             $cumulative[$metric['key']]['value'] += $metric['sum'];
          }else{
             //echo 'adding '.$metric['key'].' values old:'.$cumulative[$metric['key']]['value'].' and new reset:'.$metric['sum'];
             $cumulative[$metric['key']]['value'] = $metric['sum'];
          }

      }else{
           $cumulative[$metric['key']]['value'] = $metric['avg'];
      }
  }
  return $cumulative;
}
function saveOrPrintCumulative($cumulative){
  //print "\n==========================================================\n";
  //print "    Aggregated Metrics\n";
  //print "==========================================================\n";
  //echo 'CUMULATIVE='.serialize($cumulative);  
  foreach($cumulative as $item){
    $key = $item['key'];
    $value = $item['value'];
    $units = $item['units'];
    //print "Metric -- $key $value $units \n";
  }

}

// after every $interval minutes
// cumulate by adding current to total
// update current with grab_stats

$tablename = "Metrics";
$current = grab_stats($client, $tablename); //array();
$cumulative = array();
$cumulative  = addCurrentToCumulative($current, $cumulative);
echo 'CUMULATIVE='.serialize($cumulative)."\n";
$current = grab_stats($client, $tablename);
//Sleep($interval*60); // seconds
$cumulative  = addCurrentToCumulative($current, $cumulative);
echo 'CUMULATIVE='.serialize($cumulative)."\n";
saveOrPrintCumulative($cumulative);
//$current = grab_stats($client, $tablename);
?>
