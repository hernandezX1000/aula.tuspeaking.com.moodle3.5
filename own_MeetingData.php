<?php
require_once '/home/aulatuspeaking/www/app/moodle/own_ZoomAPI.php';
class MeetingData {
    private $meetingID;
    public function __construct(
        int $a_meetingID
    )
    {
        $this->meetingID = $a_meetingID;
    }
    public function getData() : stdClass
    {
		//$cont = 1;
        $apiData = json_decode(getZoom("https://api.zoom.us/v2/past_meetings/{$this->meetingID}"));
		//usleep(0.3);
        //$recordData = json_decode(getZoom("https://api.zoom.us/v2/meetings/{$this->meetingID}/recordings"));
		// $fichero = 'logZoom.txt';
		// $micro_date = microtime();
		// $date = date('d-m-y h:i:s');
		// $texto = $date . '.' .$micro_date .' |identoficador: '. $this->meetingID.' | ApiData: ' .json_encode($apiData );//. ' | RecordData: ' .json_encode($recordData);  
		// file_put_contents($fichero, $texto, FILE_APPEND | LOCK_EX);
		// while($apiData->code = '429' && $cont <5)
		// {
			// $apiData = json_decode(getZoom("https://api.zoom.us/v2/past_meetings/{$this->meetingID}"));
			// //usleep(0.3);
			// //$recordData = json_decode(getZoom("https://api.zoom.us/v2/meetings/{$this->meetingID}/recordings"));
			
			// $micro_date = microtime();
			// $date = date('d-m-y h:i:s');
			// $texto = $date . '.' .$micro_date .' |identoficador: '. $this->meetingID. ' | ApiData: ' .json_encode($apiData );
			// file_put_contents($fichero, $texto, FILE_APPEND | LOCK_EX);
			// $cont = $cont+1;
		// }
		//if (isset($recordData->code)){
            return $this->getInfo($apiData);
        //} else {
        //    return $this->getRecord($recordData);
        //}
    }
    private function getInfo(stdClass $data) : stdClass
    {
        $info = new stdClass();
        $info->topic = $data->topic;
        $info->start_time = $data->start_time;
        $info->duration = $data->duration;
        return $info;
    }
    private function getRecord(stdClass $data) : stdClass
    {
        $info = new stdClass();
        $info->topic = $data->topic;
        $info->start_time = $data->start_time;
        $info->end_time = $data->end_time;
        $info->duration = $data->duration;
        $info->recording_files = new stdClass();
        foreach ($data->recording_files as $key=>$value){
            $info->recording_files->$key->file_type = $value->file_type;
            $info->recording_files->$key->file_size = $this->formatBytes($value->file_size, 0);
            $info->recording_files->$key->play_url = $value->play_url;
            $info->recording_files->$key->download_url = $value->download_url;
        }
        return $info;
    }
    private function formatBytes($bytes, $precision = 2) : string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}