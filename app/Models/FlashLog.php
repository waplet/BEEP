<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\MeasurementLoRaDecoderTrait;

use App\Hive;
use App\Device;
use App\User;
use App\Measurement;
use Moment\Moment;
use Storage;
use Cache;

class FlashLog extends Model
{
    use MeasurementLoRaDecoderTrait;
    
    protected $precision  = 's';
    protected $timeFormat = 'Y-m-d H:i:s';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'flash_logs';

    /**
    * The database primary key value.
    *
    * @var string
    */
    protected $primaryKey = 'id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = ['user_id', 'device_id', 'hive_id', 'log_messages', 'log_saved', 'log_parsed', 'log_has_timestamps', 'bytes_received', 'log_file', 'log_file_stripped', 'log_file_parsed', 'log_size_bytes', 'log_erased', 'time_percentage'];

    public function hive()
    {
        return $this->belongsTo(Hive::class);
    }
    public function device()
    {
        return $this->belongsTo(Device::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function getFileContent($type='log_file')
    {
        if(isset($this->{$type}))
        {
            $file = 'flashlog/'.last(explode('/',$this->{$type}));
            //die(print_r($file));
            $disk = env('FLASHLOG_STORAGE', 'public');
            if (Storage::disk($disk)->exists($file))
                return Storage::disk($disk)->get($file);
        }
        return null;
    }

    public function log($data='', $log_bytes=null, $save=true, $fill=false, $show=false, $matches_min_override=null, $match_props_override=null, $db_records_override=null, $save_override=false, $from_cache=true, $match_days_offset=0)
    {
        if (!isset($this->device_id) || !isset($this->device))
            return ['error'=>'No device set, cannot parse Flashlog because need device key to get data from database'];

        $cache_name = 'flashlog-'.$this->id.'-fill-'.$fill.'-show-'.$show.'-matches-'.$matches_min_override.'-props-'.$match_props_override.'-dbrecs-'.$db_records_override;
        
        // get result from cache only if it contains any matches
        if ($from_cache === true && Cache::has($cache_name) && $save === false && $fill === true)
        {
            $result = Cache::get($cache_name);
            foreach ($result['log'] as $block_i => $block) 
            {
                if (isset($block['matches']))
                    return $result;
            }
        }
        
        $result   = null;
        $parsed   = false;
        $saved    = false;
        $messages = 0;

        $out   = [];
        $disk  = env('FLASHLOG_STORAGE', 'public');
        $f_dir = 'flashlog';
        $lines = 0; 
        $bytes = 0; 
        $logtm = 0;
        $erase = -1;
        
        $f_log = null;
        $f_str = null;
        $f_par = null;

        $device = $this->device;
        $sid    = $this->device_id; 
        $time   = date("YmdHis");

        if ($data && isset($sid))
        {
            $data    = preg_replace('/[\r\n|\r|\n]+|\)\(|FEFEFEFE/i', "\n", $data);
            // interpret every line as a standard LoRa message
            $in      = explode("\n", $data);
            $lines   = count($in);
            $bytes   = 0;
            $alldata = "";
            foreach ($in as $line)
            {
                $lineData = substr(preg_replace('/[^A-Fa-f0-9]/', '', $line),4);
                $alldata .= $lineData;
                $bytes   += mb_strlen($lineData, 'utf-8')/2;
            }
            
            // fw 1.5.9 1st port 2 log entry
            // 0100010005000902935D7C83FFFF94540E0123A76E05A5161AEE1F0000002A03091D01000F256079A4250A 03351B0CF10CEA640A0116539504020D8C081B0C0A094600140010002C0049001A0014005A001A0033002A07000000000000256079A6270A
            // 01000100050009029356BAA6FFFF94540E0123FA62FD38425EEE1F0000001A03091D01000F256079A25D0A 03351B0CBF0CB5640A011386550402059D0C600C0A0946005B002E0067003C0016001000160015000F000507000000000000256079A3E00A


            // Split data by 0A02 and 0A03 (0A03 30 1B) 0A0330
            $data  = preg_replace('/0A022([A-Fa-f0-9]{1})0100/', "0A\n022\${1}0100", $alldata);
            $data  = preg_replace('/0A03351B/', "0A\n03351B", $data);

            // Calculate from payload parts
            //            port pl_len     |bat 5 bytes value |weight (1/2)  3/6 bytes value  |ds18b20 (0-9) 0-20 bytes value |audio (0-12 bins)   sta/sto bin     0-12 x 2 bytes   |bme280 3x 2b values|time             |delimiter
            // $payload = '/0([0|3]{1})([A-Fa-f0-9]{2})1B([A-Fa-f0-9]{10})0A0([1-2]{1})([A-Fa-f0-9]{6,12})040([0-9]{1})([A-Fa-f0-9]{0,40})0C0([A-Ca-c0-9]{1})([A-Fa-f0-9]{4})([A-Fa-f0-9]{0,48})07([A-Fa-f0-9]{12})([A-Fa-f0-9]{0,12})0A/';
            // $replace = "\n0\${1}\${2}1B\${3}0A0\${4}\${5}040\${6}\${7}0C0\${8}\${9}\${10}07\${11}\${12}0A";

            // Calculate from payload parts
            // $payload = '/([A-Fa-f0-9]{4})1B([A-Fa-f0-9]{10})0A0([1-2]{1})([A-Fa-f0-9]{6,12})040([0-9]{1})([A-Fa-f0-9]{0,40})0C0([A-Ca-c0-9]{1})([A-Fa-f0-9]{4})([A-Fa-f0-9]{0,48})07([A-Fa-f0-9]{12})([A-Fa-f0-9]{0,12})0A/';
            // $replace = "\n\${1}1B\${2}0A0\${3}\${4}040\${5}\${6}0C0\${7}\${8}\${9}07\${10}\${11}0A";

            // Calculate from payload min/max length
            $payload = '/([A-Fa-f0-9]{4})1B([A-Fa-f0-9]{10,14})0A([A-Fa-f0-9]{77,90})0A/';
            
            $replace = "\n\${1}1B\${2}0A0\${3}0A";
            
            $data    = preg_replace($payload, $replace, $data);

            // fix missing battery hex code
            $data  = preg_replace('/0A03([A-Fa-f0-9]{2})([A-Fa-f0-9]{2})0D/', "0A\n03\${1}1B0D\${2}0D", $data);
            // split error lines
            $data  = preg_replace('/03([A-Fa-f0-9]{90,120})0A([A-Fa-f0-9]{0,4})03([A-Fa-f0-9]{90,120})0A/', "03\${1}0A\${2}\n03\${3}0A", $data);
            $data  = preg_replace('/03([A-Fa-f0-9]{90,120})0A1B([A-Fa-f0-9]{90,120})0A/', "03\${1}0A\n031E1B\${2}0A", $data); // missing 031E
            $data  = preg_replace('/02([A-Fa-f0-9]{76})0A03([A-Fa-f0-9]{90,120})0A/', "02\${1}0A\n03\${2}0A", $data);


            if ($save)
            {
                $logFileName =  $f_dir."/sensor_".$sid."_flash_stripped_$time.log";
                $saved = Storage::disk($disk)->put($logFileName, $data);
                $f_str = Storage::disk($disk)->url($logFileName); 
            }

            $counter = 0;
            $in      = explode("\n", $data);
            $log_min = 0;
            $minute  = 0;
            $max_time= time();

            // load device sensor definitions
            $sensor_defs     = $device->activeSensorDefinitions();
            $sensor_defs_all = $device->sensorDefinitions;

            foreach ($in as $line)
            {
                $counter++;
                $data_array = $this->decode_flashlog_payload($line, $show);
                $data_array['i'] = $counter;

                if ($data_array['port'] == 3) // port 3 message is log message
                    $messages++;
                
                if (isset($data_array['measurement_interval_min']))
                {
                    $log_min = $data_array['measurement_interval_min'];
                }
                else
                {
                    $minute += $log_min;
                    $data_array['minute'] = $minute;
                    $data_array['minute_interval'] = $log_min;
                }

                // Add time if not present
                if (!isset($data_array['time']) && isset($data_array['time_device']))
                {
                    $logtm++;
                    $ts = intval($data_array['time_device']);

                    if ($ts > 1546297200 && $ts < $max_time) // > 2019-01-01 00:00:00 < now
                    {
                        $time_device = new Moment($ts);
                        $data_array['time'] = $time_device->format($this->timeFormat);
                    }
                }

                // Add sensor definition measurement if not yet present (or if input_measurement_id == output_measurement_id) 
                foreach ($sensor_defs as $sd) 
                {
                    if (isset($sd->output_abbr) && isset($data_array[$sd->input_abbr]) && (!isset($data_array[$sd->output_abbr]) || $sd->input_measurement_id == $sd->output_measurement_id))
                    {
                        $date       = isset($data_array['time']) ? $data_array['time'] : null;
                        $data_array = $device->addSensorDefinitionMeasurements($data_array, $data_array[$sd->input_abbr], $sd->input_measurement_id, $date, $sensor_defs_all);
                    }
                }

                $out[] = $data_array;
            }

            if ($messages > 0)
            {
                $parsed = true;
                if ($save)
                {
                    $logFileName = $f_dir."/sensor_".$sid."_flash_parsed_$time.json";
                    $saved = Storage::disk($disk)->put($logFileName, json_encode($out));
                    $f_par = Storage::disk($disk)->url($logFileName);
                }
            }
        }

        $erase = $log_bytes != null && $log_bytes == $bytes ? true : false;
        $result = [
            'lines_received'=>$lines,
            'bytes_received'=>$bytes,
            'log_size_bytes'=>$log_bytes,
            'log_has_timestamps'=>$logtm,
            'log_saved'=>$saved,
            'log_parsed'=>$parsed,
            'log_messages'=>$messages,
            'erase_mx_flash'=>$erase ? 0 : -1,
            'erase'=>$erase,
            'erase_type'=>$saved ? 'fatfs' : null // fatfs, or full
        ];

        // fill time in unknown time data 
        $time_percentage = $messages > 0 ? round(100 * $logtm / $messages, 2) : 0;

        if ($fill && count($out) > 0)
        {
            $flashlog_filled = $this->fillDataGaps($device, $out, $save, $show, $matches_min_override, $match_props_override, $db_records_override, $match_days_offset); // ['time_percentage'=>$time_percentage, 'records_timed'=>$records_timed, 'records_flashlog'=>$records_flashlog, 'time_insert_count'=>$setCount, 'flashlog'=>$flashlog];
            
            if ($flashlog_filled)
            {
                if (isset($flashlog_filled['log']))
                    $result['log'] = $flashlog_filled['log'];

                $result['matching_blocks']   = $flashlog_filled['matching_blocks'];
                $result['device']            = $device->name.' ('.$sid.')';
                $result['records_flashlog']  = $flashlog_filled['records_flashlog'];
                $result['time_insert_count'] = $flashlog_filled['time_insert_count'];
                $result['records_timed']     = $flashlog_filled['records_timed'];
                $weight_percentage           = round($flashlog_filled['weight_percentage'], 2);
                $result['weight_percentage'] = $weight_percentage.'%';
                $result['time_insert_count'] = $flashlog_filled['time_insert_count'];
                $time_percentage             = round($flashlog_filled['time_percentage'], 2);
                $result['time_percentage']   = $time_percentage.'%';

                if (isset($this->time_percentage) == false || (min(100, $this->time_percentage*0.9) <= $time_percentage || $save_override) || $this->time_percentage > 100)
                {
                    if ( ($save || $result['matching_blocks'] > 0) && isset($flashlog_filled['flashlog']) && count($flashlog_filled['flashlog']) > 0 && $flashlog_filled['time_insert_count'] > 0)
                    {
                        $save        = true;
                        $logFileName = $f_dir."/sensor_".$sid."_flash_filled_$time.json";
                        $saved       = Storage::disk($disk)->put($logFileName, json_encode($flashlog_filled['flashlog']));
                        $f_par       = Storage::disk($disk)->url($logFileName);
                    }
                }
                else
                {
                    $result['time_percentage'] .= ', previous time percentage ('.$this->time_percentage.'%) > new ('.$time_percentage.'%)';
                    if ($save)
                        $result['time_percentage'] .= ', so filled file not saved';

                    $time_percentage = $this->time_percentage;
                    $f_par           = $this->log_file_parsed;
                }

            }
        }

        // create Flashlog entity
        if ($save)
        {
            if (isset($this->log_size_bytes) == false) // first upload 
                $this->log_size_bytes = $log_bytes;

            if (isset($this->hive_id) == false) // first upload 
                $this->hive_id = $device->hive_id;

            if (isset($this->log_erased) == false) // first upload 
                $this->log_erased = $erase;

            if (isset($this->log_saved) == false) // first upload 
                $this->log_saved = $saved;
            
            $this->bytes_received = $bytes;
            $this->log_has_timestamps = $logtm > 0 ? true : false;
            $this->log_parsed = $parsed;
            $this->log_messages = $messages;
            $this->log_file_stripped = $f_str;
            $this->log_file_parsed = $f_par;
            $this->time_percentage = $time_percentage;
            $this->save();
        }

        Cache::put($cache_name, $result, 86400); // keep for a day

        return $result;
    }


    // Flashlog parsing functions
    private function getFlashLogOnOffs($flashlog, $start_index=0, $start_time='2018-01-01 00:00:00')
    {
        $onoffs      = [];
        $fl_index    = $start_index;
        $fl_index_end= count($flashlog) - 1;

        for ($i=$fl_index; $i < $fl_index_end; $i++) 
        {
            $f = $flashlog[$i];
            if ($f['port'] == 2 && isset($f['beep_base'])) // check for port 2 messages (switch on/off) in between 'before' and 'after' matches
            {
                $onoffs[$i] = $f;
                $onoffs[$i]['block_count'] = 1;
                if (isset($onoffs[$i-1]))
                {
                    $onoffs[$i]['block_count'] = $onoffs[$i-1]['block_count'] + 1; // count amount of messages in a row
                    unset($onoffs[$i-1]); // make sure only the last on/off message of every block is returned (if it has the same interval)
                }
            }
        }
        return array_values($onoffs);
    }

    private function matchFlashLogTime($device, $flashlog, $matches_min=1, $match_props=9, $start_index=0, $end_index=0, $duration_hrs=0, $interval_min=15, $start_time='2018-01-01 00:00:00', $db_records=80, $show=false)
    {
        $fl_index    = $start_index;
        $fl_index_end= $end_index;
        $fl_items    = max(0, $end_index - $start_index);
        $day_props_m = isset($interval_min) ? 24 * 60 / $interval_min : 96; // max total measurements per match prop on a full data day
        $day_total_m = $match_props * $day_props_m; // max total measurements for all match_props on a full data day
        
        if ($flashlog == null || $fl_items < $matches_min)
            return ['fl_index'=>$fl_index, 'fl_index_end'=>$fl_index_end, 'db_start_time'=>$start_time, 'db_data'=>[], 'db_data_count'=>0, 'message'=>'too few flashlog items to match: '.$fl_items];
        
        // Check the amount of data per day over the full length of the period, check for the date with the maximum amount of measurements
        $start_mom   = new Moment($start_time);
        $end_time    = $start_mom->addHours(round($duration_hrs))->format($this->timeFormat);

        $count_query = 'SELECT COUNT(*) FROM "sensors" WHERE '.$device->influxWhereKeys().' AND time >= \''.$start_time.'\' AND time <= \''.$end_time.'\' GROUP BY time(24h) ORDER BY time ASC LIMIT 500';
        $data_count  = Device::getInfluxQuery($count_query, 'flashlog');
        $day_sum_max = 0;
        $days_valid  = 0;

        $db_data_cnt = 0;
        foreach ($data_count as $day_i => $count_array) 
        {
            $data_date    = $count_array['time'];
            unset($count_array['time']); // don't include time in sum
            $day_sum      = array_sum($count_array);
            $day_valid    = ($day_sum > 0.7*$day_total_m); // magic factor 0.7
            $db_data_cnt += $day_sum; // fill db_data_cnt for providing insight in where to fill flashlog data (sum == 0)
            
            if ($day_valid && $days_valid < 2)
            {
                $days_valid++;
                if ($days_valid == 2) // day after first valid day
                    $start_time = $data_date;

            }
        }
        
        //die(print_r([$start_time, $end_time, $duration_hrs, count($data_count), $db_data_cnt]));

        // get data from the day with max amount of measurements
        $query       = 'SELECT * FROM "sensors" WHERE '.$device->influxWhereKeys().' AND time >= \''.$start_time.'\' ORDER BY time ASC LIMIT '.min(500, max($matches_min, $db_records));
        $db_data     = Device::getInfluxQuery($query, 'flashlog');

        //die(print_r([$start_time, $db_data]));
        
        $database_log  = [];
        $db_first_unix = 0;
        foreach ($db_data as $d)
        {
            $clean_d = array_filter($d);
            unset($clean_d['hardware_id']);
            unset($clean_d['device_name']);
            unset($clean_d['key']);
            unset($clean_d['user_id']);
            unset($clean_d['rssi']);
            unset($clean_d['snr']);

            if (count($clean_d) > $match_props && array_sum(array_values($clean_d)) != 0)
            {
                if (count($database_log) == 0) // first entry
                {
                    $db_first_unix      = strtotime($clean_d['time']);
                    $clean_d['unix']    = $db_first_unix;
                    $clean_d['seconds'] = 0;
                }

                $clean_d['unix']    = strtotime($clean_d['time']);
                $clean_d['seconds'] = $clean_d['unix'] - $db_first_unix;

                $database_log[] = $clean_d;

                //die(print_r([$start_time, $clean_d, count($clean_d), $db_data]));
            }

        }

        if (count($database_log) < $matches_min)
            return ['fl_index'=>$fl_index, 'fl_index_end'=>$fl_index_end, 'db_start_time'=>$start_time, 'db_data_measurements'=>$db_data_cnt, 'db_data_count'=>count($database_log), 'message'=>'too few database items to match: '.count($database_log)];

        // look for the measurement value(s) in $database_log in the remainder of $flashlog
        $matches            = [];
        $tries              = 0;
        $match_count        = 0;
        $match_first_min_fl = 0;
        $match_first_sec_db = 0;
        foreach ($database_log as $d)
        {
            if ($match_count >= $matches_min)
                return ['fl_index'=>$fl_index, 'fl_index_end'=>$fl_index_end, 'fl_match_tries'=>$tries, 'db_start_time'=>$start_time, 'db_data_measurements'=>$db_data_cnt, 'db_data_count'=>count($database_log), 'matches'=>$matches];

            for ($i=$fl_index; $i < $fl_index_end; $i++) 
            {
                $f = $flashlog[$i];

                if ($f['port'] == 3 && $match_count < $matches_min) // keep looking if found matches are < min matches
                {
                    $match = array_intersect_assoc($d, $f);
                    if ($match != null && count($match) >= $match_props)
                    {
                        if ($match_count == 0)
                        {
                            $match_first_min_fl = $f['minute'];
                            $match_first_sec_db = $d['seconds'];
                        }
                        else if ($match_count == 1) // check the time interval of the match in 2nd match
                        {
                            $match_increase_min = $f['minute'] - $match_first_min_fl;
                            $datab_increase_min = round( ($d['seconds'] - $match_first_sec_db) / 60);

                            //print_r([$match_increase_min, $datab_increase_min, $matches]);

                            if ($match_increase_min != $datab_increase_min) // if this does not match, remove first match and replace by this one
                            {
                                $match_first_sec_db = $d['seconds'];
                                $match_first_min_fl = $f['minute'];
                                $matches            = [];
                                $match_count        = 0;
                            }
                        }

                        $fl_index                 = $i;
                        $match['time']            = $d['time'];
                        $match['minute']          = $f['minute'];
                        $match['minute_interval'] = $f['minute_interval'];  
                        $match['flashlog_index']  = $i;
                        $matches[$i]              = $match;
                        $match_count++;
                        continue 2; // next foreach loop to continue with the next database item

                        // TODO: do not break the loop to check how many matches there are
                    }
                    $tries++;
                }


            }
        }
        //die();
        return ['fl_index'=>$fl_index, 'fl_index_end'=>$fl_index_end, 'fl_match_tries'=>$tries, 'db_start_time'=>$start_time, 'db_data_measurements'=>$db_data_cnt, 'db_data_count'=>count($db_data), 'message'=>'no matches found'];
    }

    private function setFlashBlockTimes($match, $blockInd, $startInd, $endInd, $flashlog, $device, $show=false)
    {
        if (isset($match) && isset($match['flashlog_index']) && isset($match['minute_interval']) && isset($match['time'])) // set times for current block
        {
            $matchInd= $match['flashlog_index'];
            $messages= $endInd - $startInd;
            $setCount= 0;
            
            if ($messages > 0)
            {
                $blockStaOff = $startInd - $matchInd;
                $blockEndOff = $endInd - $matchInd;
                $matchMinInt = $match['minute_interval'];
                $matchTime   = $match['time'];
                $matchMoment = new Moment($matchTime);
                $startMoment = new Moment($matchTime);
                $endMoment   = new Moment($matchTime);
                $matchTime   = $matchMoment->format($this->timeFormat);
                $blockStart  = $startMoment->addMinutes($blockStaOff * $matchMinInt);
                $blockStaDate= $blockStart->format($this->timeFormat);
                $blockEnd    = $endMoment->addMinutes($blockEndOff * $matchMinInt);
                $blockEndDate= $blockEnd->format($this->timeFormat);

                // load device sensor definitions
                $sensor_defs     = $device->activeSensorDefinitions();
                $sensor_defs_all = $device->sensorDefinitions;

                //die(print_r(['matchTime'=>$matchTime, 'matchMinInt'=>$matchMinInt, 'startInd'=>$startInd, 'blockStaDate'=>$blockStaDate, 'blockEndDate'=>$blockEndDate, 'match'=>$match]));
                // add time to flashlog block
                $setTimeStart = '';
                $setTimeEnd   = '';
                $addCounter   = 0;
                for ($i=$startInd; $i <= $endInd; $i++) 
                { 
                    $fl = $flashlog[$i];

                    // Add time if not present
                    // if (!isset($fl['time']))
                    // {
                        $startMoment= new Moment($blockStaDate);
                        $indexMoment= $startMoment->addMinutes($addCounter * $matchMinInt);
                        $fl['time'] = $indexMoment->format($this->timeFormat);

                        // check for time_device, replace time with device time if less than 60 seconds off
                        // if (isset($fl['time_device']))
                        // {
                        //     $second_deviation = abs($indexMoment->format('U') - $fl['time_device']);
                        //     if ($second_deviation < 60)
                        //     {
                        //         $time_device = new Moment($fl['time_device']);
                        //         $fl['time_device_readable'] = $time_device->format($this->timeFormat);
                        //         //die(print_r(['fl'=>$fl, 'i'=>$i, 'blockStaOff'=>$blockStaOff, 'blockEndOff'=>$blockEndOff]));
                        //     }
                        // }
                    //}

                    // Add sensor definition measurement if not yet present (or if input_measurement_id == output_measurement_id) 
                    if (isset($fl['time']))
                    {
                        foreach ($sensor_defs as $sd) 
                        {
                            if (isset($sd->output_abbr) && isset($fl[$sd->input_abbr]) && (!isset($fl[$sd->output_abbr]) || $sd->input_measurement_id == $sd->output_measurement_id))
                                $fl = $device->addSensorDefinitionMeasurements($fl, $fl[$sd->input_abbr], $sd->input_measurement_id, $fl['time'], $sensor_defs_all);
                        }
                    }
                    
                    if ($fl['port'] == 3)
                        $setCount++;

                    $flashlog[$i] = $fl;

                    $addCounter++;
                }

                $log = null;
                if ($show)
                {
                    // add request for database values per day
                    $log = ['setFlashBlockTimes', 'block_i'=>$blockInd, 'time0'=>$flashlog[$startInd]['time'], 'time1'=>$flashlog[$endInd]['time'], 'bl_start_i'=>$startInd, 'bl_end_i'=>$endInd, 'match_time'=>$matchTime, 'mi'=>$matchInd, 'min_int'=>$matchMinInt, 'msg'=>$messages, 'bso'=>$blockStaOff, 'bsd'=>$blockStaDate, 'beo'=>$blockEndOff, 'bed'=>$blockEndDate,'setCount'=>$setCount];
                }
                
                $dbCount = $device->getMeasurementCount($blockStaDate, $blockEndDate);
                // TODO: Add check for every timestamp in DB with matching Flashlog (for bv, w_v, (t_0, t_1, or t_i))
                return ['flashlog'=>$flashlog, 'index_start'=>$startInd, 'index_end'=>$endInd, 'time_start'=>$blockStaDate, 'time_end'=>$blockEndDate, 'setCount'=>$setCount, 'log'=>$log, 'dbCount'=>$dbCount];
            }
        }
        return ['flashlog'=>$flashlog];
    }

    private function matchFlashLogBlock($i, $fl_index, $end_index, $on, $flashlog, $setCount, $device, $log, $db_time, $matches_min, $match_props, $db_records, $show=false)
    {
        $has_matches     = false;
        $block_index     = $on['i'];
        $start_index     = $block_index+1;
        $interval        = intval($on['measurement_interval_min']); // transmission ratio is not of importance here, because log contains all measurements

        $db_moment       = new Moment($db_time);
        
        $indexes         = max(0, $end_index - $start_index);
        $duration_min    = $interval * $indexes;
        $duration_hrs    = round($duration_min / 60, 1);

        // check if database query should be based on the device time, or the cached time from the 
        $use_device_time = false;
        if (isset($flashlog[$start_index]['time_device']))   
        {
            $device_moment = new Moment($flashlog[$start_index]['time_device']);
            $device_time   = $device_moment->format($this->timeFormat);
            if ($device_time > $db_time)
            {
                $db_time         = $device_time;
                $db_moment       = new Moment($db_time);
                $use_device_time = true;
            }
        }
        
        // Disable half time checking, because will be solved in matchFlashLogTime
        // set time to 1/2 of interval if > 2 * amount of indexes 
        // if ($indexes > 2 * $db_records && $use_device_time === false) 
        // {
        //     $db_q_time = $db_moment->addMinutes(round($duration_min/2))->format($this->timeFormat);
        //     $db_max    = max($matches_min, min($db_records, $indexes/2));
        // }
        // else
        // {
            $db_q_time = $db_time;
            $db_max    = max($matches_min, min($db_records, $indexes));
        // }

        // matchFlashLogTime returns: ['fl_index'=>$fl_index, 'fl_index_end'=>$fl_index_end, 'fl_match_tries'=>$tries, 'db_start_time'=>$start_time, 'db_data'=>$db_data, 'db_data_count'=>count($db_data), 'matches'=>$matches];
        $matches = $this->matchFlashLogTime($device, $flashlog, $matches_min, $match_props, $start_index, $end_index, $duration_hrs, $interval, $db_q_time, $db_max, $show);
        
        if (isset($matches['matches']))
        {
            $match = reset($matches['matches']); // take first match for matching the time
            //$match_last = end($matches); // take last match
            if (isset($match['flashlog_index']) && isset($match['time']))
            {
                $fl_index = $match['flashlog_index'];
                //die(print_r($match));
                $block    = $this->setFlashBlockTimes($match, $block_index, $start_index, $end_index, $flashlog, $device);
                $flashlog = $block['flashlog'];

                if (isset($block['index_end']))
                {
                    // A match is found
                    $has_matches = true;
                    if ($show)
                        $log[] = ['fillDataGaps', 'block'=> $i, 'block_i'=>$block_index, 'start_i'=>$start_index, 'end_i'=>$end_index, 'duration_hours'=>$duration_hrs, 'fl_i'=>$fl_index, 'db_time'=>$db_time, 'fw_version'=>$on['firmware_version'], 'interval_min'=>$on['measurement_interval_min'], 'transmission_ratio'=>$on['measurement_transmission_ratio'], 'index_start'=>$block['index_start'], 'index_end'=>$block['index_end'], 'time_start'=>$block['time_start'], 'time_end'=>$block['time_end'], 'setCount'=>$block['setCount'], 'matches'=>$matches, 'dbCount'=>$block['dbCount']];

                    $setCount += $block['setCount'];
                    $db_time  = $block['time_end'];
                    $fl_index = $block['index_end'];
                }
                else
                {
                    if ($show)
                        $log[] = ['block'=> $i, 'block_i'=>$block_index, 'start_i'=>$start_index, 'end_i'=>$end_index, 'duration_hours'=>$duration_hrs, 'fl_i'=>$fl_index, 'db_time'=>$db_time, 'fw_version'=>$on['firmware_version'], 'interval_min'=>$on['measurement_interval_min'], 'transmission_ratio'=>$on['measurement_transmission_ratio']];

                    $db_time = $db_moment->addMinutes($duration_min)->format($this->timeFormat);
                }
            }
            else
            {
                //die(print_r($matches));
                if ($show)
                    $log[] = ['block'=> $i, 'block_i'=>$block_index, 'start_i'=>$start_index, 'end_i'=>$end_index, 'duration_hours'=>$duration_hrs, 'fl_i'=>$fl_index, 'db_time'=>$db_time, 'fw_version'=>$on['firmware_version'], 'interval_min'=>$on['measurement_interval_min'], 'transmission_ratio'=>$on['measurement_transmission_ratio'], 'no_matches'=>'fl_i and time of match not set', 'match'=>$matches];

                $db_time = $db_moment->addMinutes($duration_min)->format($this->timeFormat);
            }
        }
        else
        {
            if ($show)
                $log[] = ['block'=> $i, 'block_i'=>$block_index, 'start_i'=>$start_index, 'end_i'=>$end_index, 'duration_hours'=>$duration_hrs, 'fl_i'=>$fl_index, 'db_time'=>$db_time, 'fw_version'=>$on['firmware_version'], 'interval_min'=>$on['measurement_interval_min'], 'transmission_ratio'=>$on['measurement_transmission_ratio'], 'no_matches'=>$matches];

            $db_time = $db_moment->addMinutes($duration_min)->format($this->timeFormat);
        }
        return ['has_matches'=>$has_matches, 'flashlog'=>$flashlog, 'db_time'=>$db_time, 'log'=>$log, 'fl_index'=>$fl_index, 'setCount'=>$setCount];
    }


    /* Flashlog data correction algorithm
    1. Match time ascending database data to Flash log data of BEEP bases
    2. Match a number of (3) measurements in a row with multiple (9) exact matches to find the correct time of the log data
    3. Align the Flash log time for all 'blocks' of port 3 (measurements) between port 2 (on/off) records  
    4. Save as a filled file
    */
    private function fillDataGaps($device, $flashlog=null, $save=false, $show=false, $matches_min_override=null, $match_props_override=null, $db_records_override=null, $match_days_offset=0)
    {
        $out         = [];
        $matches_min = env('FLASHLOG_MIN_MATCHES', 5); // minimum amount of inline measurements that should be matched 
        $match_props = env('FLASHLOG_MATCH_PROPS', 9); // minimum amount of measurement properties that should match 
        $db_records  = env('FLASHLOG_DB_RECORDS', 80);// amount of DB records to fetch to match each block

        if (isset($matches_min_override))
            $matches_min = $matches_min_override;

        if (isset($match_props_override))
            $match_props = $match_props_override;

        if (isset($db_records_override))
            $db_records = $db_records_override;

        if ($flashlog == null || count($flashlog) < $matches_min)
            return null;

        $fl_index = 0;
        $db_time  = '2019-01-01 00:00:00'; // start before any BEEP bases were live
        $setCount = 0;
        $log      = [];
        $on_offs  = $this->getFlashLogOnOffs($flashlog, $fl_index);
        //die(print_r($on_offs));
        $device_id= $device->id;
        $matches  = 0;

        foreach ($on_offs as $i => $on)
        {
            $end_index    = $i < count($on_offs)-1 ? $on_offs[$i+1]['i']-1 : count($flashlog)-1;
            // if ($start_index >= $fl_index)
            // {
            $matchBlockResult = $this->matchFlashLogBlock($i, $fl_index, $end_index, $on, $flashlog, $setCount, $device, $log, $db_time, $matches_min, $match_props, $db_records, $show);
            $flashlog         = $matchBlockResult['flashlog'];
            $db_time          = $matchBlockResult['db_time'];
            $log              = $matchBlockResult['log'];
            $setCount         = $matchBlockResult['setCount'];
            $fl_index         = $matchBlockResult['fl_index'];
            $matches         += $matchBlockResult['has_matches'] ? 1 : 0;
            // }
            // else
            // {
            //     if ($show)
            //         $log[] = ['block'=> $i, 'block_i'=>$block_index, 'start_i'=>$start_index, 'end_i'=>$end_index, 'duration_hours'=>$duration_hrs, 'fl_i'=>$fl_index, 'db_time'=>$db_time, 'fw_version'=>$on['firmware_version'], 'interval_min'=>$on['measurement_interval_min'], 'transmission_ratio'=>$on['measurement_transmission_ratio'], 'no_matches'=>'start_index < fl_index'];
            // }
        }

        $records_flashlog = 0;
        $records_timed    = 0;
        $records_weight   = 0;
        foreach ($flashlog as $f) 
        {
            if ($f['port'] == 3)
            {
                $records_flashlog++;
                
                if (isset($f['time']))
                    $records_timed++;

                if (isset($f['weight_kg']))
                    $records_weight++;
            }

        }
        $time_percentage = $records_flashlog > 0 ? min(100, 100*($records_timed/$records_flashlog)) : 0;
        $weight_percentage = $records_flashlog > 0 ? min(100, 100*($records_weight/$records_flashlog)) : 0;
        $out = ['matching_blocks'=>$matches, 'time_percentage'=>$time_percentage, 'records_timed'=>$records_timed, 'weight_percentage'=>$weight_percentage, 'records_flashlog'=>$records_flashlog, 'time_insert_count'=>$setCount, 'flashlog'=>$flashlog];

        if ($show)
        {
            $out['log'] = $log;
            $out['matches_min'] = $matches_min;
            $out['match_props'] = $match_props;
            $out['db_records']  = $db_records;
        }

        return $out;
    }

}
