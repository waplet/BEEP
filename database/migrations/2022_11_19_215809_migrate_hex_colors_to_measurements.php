<?php

use Illuminate\Database\Migrations\Migration;

class MigrateHexColorsToMeasurements extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $colorMap = [
            "w_v" => 'DAA520',
            "t" => 'DB7093',
            "h" => '191970',
            "l" => 'FFD700',
            "p" => '556B2F',
            "bv" => '2F4F4F',
            "s_fan_4" => '87CEEB',
            "s_fan_6" => '9ACD32',
            "s_fan_9" => '556B2F',
            "s_fly_a" => 'BA55D3',
            "s_tot" => '000000',
            "s_spl" => '000000',
            "bc_i" => 'BA55D3',
            "bc_o" => 'DB7093',
            "bc_tot" => '000000',
            "weight_kg" => 'FFA500',
            "weight_kg_corrected" => '696969',
            "t_i" => 'DC143C',
            "t_0" => 'DC143C',
            "t_1" => 'DC143C',
            "t_2" => 'DC143C',
            "t_3" => 'DC143C',
            "t_4" => 'DC143C',
            "t_5" => 'DC143C',
            "t_6" => 'DC143C',
            "t_7" => 'DC143C',
            "t_8" => 'DC143C',
            "t_9" => 'DC143C',
            "rssi" => 'C0C0C0',
            "snr" => 'D3D3D3',
            "lat" => 'DCDCDC',
            "lon" => 'DCDCDC',
            "s_bin098_146Hz" => '556B2F',
            "s_bin146_195Hz" => '9ACD32',
            "s_bin195_244Hz" => '87CEEB',
            "s_bin244_293Hz" => '191970',
            "s_bin293_342Hz" => 'BA55D3',
            "s_bin342_391Hz" => 'DB7093',
            "s_bin391_439Hz" => 'DC143C',
            "s_bin439_488Hz" => 'FFA500',
            "s_bin488_537Hz" => 'FFD700',
            "s_bin537_586Hz" => 'DCDCDC',
            "s_bin_71_122" => '556B2F',
            "s_bin_122_173" => '9ACD32',
            "s_bin_173_224" => '87CEEB',
            "s_bin_224_276" => '191970',
            "s_bin_276_327" => 'BA55D3',
            "s_bin_327_378" => 'DB7093',
            "s_bin_378_429" => 'DC143C',
            "s_bin_429_480" => 'FFA500',
            "s_bin_480_532" => 'FFD700',
            "s_bin_532_583" => 'DCDCDC',
            "s_bin_0_201" => '556B2F',
            "s_bin_201_402" => '9ACD32',
            "s_bin_402_602" => '87CEEB',
            "s_bin_602_803" => '191970',
            "s_bin_803_1004" => 'BA55D3',
            "s_bin_1004_1205" => 'DB7093',
            "s_bin_1205_1406" => 'DC143C',
            "s_bin_1406_1607" => 'FFA500',
            "s_bin_1607_1807" => 'FFD700',
            "s_bin_1807_2008" => 'DCDCDC',
            "icon" => 'DB7093',
            "precipIntensity" => '191970',
            "precipProbability" => '191970',
            "precipType" => '191970',
            "temperature" => 'DC143C',
            "apparentTemperature" => 'DB7093',
            "dewPoint" => 'DB7093',
            "humidity" => '87CEEB',
            "pressure" => '9ACD32',
            "windSpeed" => 'C0C0C0',
            "windGust" => '87CEEB',
            "windBearing" => '87CEEB',
            "cloudCover" => 'D3D3D3',
            "uvIndex" => 'DB7093',
            "visibility" => 'DCDCDC',
            "ozone" => 'C0C0C0',
            "alarm_state" => 'C0C0C0',
            "button_state" => 'C0C0C0',
            "reed_switch_state" => 'C0C0C0',
            "bee_power_state" => 'C0C0C0',
            "food_state" => 'C0C0C0',
            "led3_state" => 'C0C0C0',
            "alarm_out" => 'DC143C',
        ];

        foreach ($colorMap as $sensor => $hexColor) {
            \App\Measurement::where('abbreviation', $sensor)
                ->update(['hex_color' => $hexColor]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ;
    }
}
