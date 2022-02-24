<?php

use Illuminate\Database\Seeder;

class WeatherMeasurementSeeder extends Seeder
{
    private const WEATHER = [
        'temperature' => [
            'abbreviation' => 'temperature',
            'show_in_charts' => 1,
            'physical_quantity_id' => 'Temperature',
            'weather' => 1,
        ],
        'pressure' => [
            'abbreviation' => 'pressure',
            'show_in_charts' => 1,
            'physical_quantity_id' => 'Pressure',
            'weather' => 1,
        ],
        'humidity' => [
            'abbreviation' => 'humidity',
            'show_in_charts' => 1,
            'physical_quantity_id' => 'Humidity',
            'weather' => 1,
        ],
        'windSpeed' => [
            'abbreviation' => 'windSpeed',
            'show_in_charts' => 1,
            'physical_quantity_id' => [
                'name' => 'Speed',
                'unit' => 'm/s',
                'abbreviation' => 'm/s',
            ],
            'weather' => 1,
        ],
        'alarm_out' => [
            'abbreviation' => 'alarm_out',
            'show_in_charts' => 1,
            'show_in_dials' => 1,
            'show_in_alerts' => 1,
            'physical_quantity_id' => '-',
            'weather' => 0,
        ],


    //     [temperature] => -2.01
    // [temperatureMin] => -3.43
    // [temperatureMax] => -2.01
    // [apparentTemperature] => -4.88
    // [pressure] => 1016
    // [humidity] => 93
    // [visibility] => 10000
    // [windSpeed] => 2.06
    // [windBearing] => 220
    // [cloudiness] => 0
    ];
    
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (self::WEATHER as $abbrev => $data) {
            $measurement = \App\Measurement::where(['abbreviation' => $abbrev])->first();
            
            if ($measurement) {
                // TODO: update
                continue;
            }
            
            $isArray = is_array($data['physical_quantity_id']);
            $name =  $isArray 
                ? $data['physical_quantity_id']['name'] 
                : $data['physical_quantity_id'];
            $physicalQuantity = \App\PhysicalQuantity::where(['name' => $name])->first();
            if (!$physicalQuantity) {
                // TODO: add?
                if (!$isArray) {
                    continue;
                }
                
                // add
                $physicalQuantity = \App\PhysicalQuantity::create($data['physical_quantity_id']);
            }
            
            $data['physical_quantity_id'] = $physicalQuantity->id;
            
            $measurement = \App\Measurement::create($data);
        }
    }
}
