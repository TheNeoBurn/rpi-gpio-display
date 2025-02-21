<?php

$cmd = $_GET['cmd'];

switch ($cmd) {
    case 'mpu6050_init':
        $addr = $_GET['addr'] ?? die('No address!');
        if (preg_match("/^[0-9]{1,3}$/", $addr) == 1) {
            $addr = intval($addr);
            if ($addr > 255) die('Address out of range! ('.$addr.')');
            $addr = '0x' . str_pad(dechex($addr), 2, '0', STR_PAD_LEFT);
        } else if (preg_match("/^0x[0-9a-f]{2}$/i", $addr) == 1) {
            // keep $addr as is
        } else {
            die("Invalid pin name $addr!");
        }
        $shellcmd = 'i2cset -y 1 ' . $addr . ' 0x19 0x07' . // Write to sample rate register
                ' && i2cset -y 1 ' . $addr . ' 0x6B 0x01' . // Write to power management register
                ' && i2cset -y 1 ' . $addr . ' 0x1A 0x00' . // Write to Configuration register
                ' && i2cset -y 1 ' . $addr . ' 0x1B 0x24' . // Write to Gyro configuration register
                ' && i2cset -y 1 ' . $addr . ' 0x38 0x00';  // Write to interrupt enable register (off)

        $result = array(
            'result' => shell_exec($shellcmd)
        );

        header('Content-Type: application/json; charset=utf-8');
        echo(json_encode($result));
        break;

    case 'mpu6050_get':
        $addr = $_GET['addr'] ?? die('No address!');
        if (preg_match("/^[0-9]{1,3}$/", $addr) == 1) {
            $addr = intval($addr);
            if ($addr > 255) die('Address out of range! ('.$addr.')');
            $addr = '0x' . str_pad(dechex($addr), 2, '0', STR_PAD_LEFT);
        } else if (preg_match("/^0x[0-9a-f]{2}$/i", $addr) == 1) {
            // keep $addr as is
        } else {
            die("Invalid pin name $addr!");
        }
        $shellcmd = 'i2cget -y 1 ' . $addr . ' 0x3B i 6' . 
                ' && i2cget -y 1 ' . $addr . ' 0x43 i 6';
        $values = preg_split('/ |\\n/', shell_exec( $shellcmd), -1, PREG_SPLIT_NO_EMPTY);
        $result = array( 'success' => false );
        if (count($values) == 12) {
            $accelX = hexdec(preg_replace('/0x/', '', $values[0] . $values[1]));
            $accelY = hexdec(preg_replace('/0x/', '', $values[2] . $values[3]));
            $accelZ = hexdec(preg_replace('/0x/', '', $values[4] . $values[5]));
            // Convert unsigned to signed short
            $accelX = unpack('s', pack('S', $accelX))[1];
            $accelY = unpack('s', pack('S', $accelY))[1];
            $accelZ = unpack('s', pack('S', $accelZ))[1];

            $gyroX = hexdec(preg_replace('/0x/', '', $values[6] . $values[7]));
            $gyroY = hexdec(preg_replace('/0x/', '', $values[8] . $values[9]));
            $gyroZ = hexdec(preg_replace('/0x/', '', $values[10] . $values[11]));
            // Convert unsigned to signed short
            $gyroX = unpack('s', pack('S', $gyroX))[1];
            $gyroY = unpack('s', pack('S', $gyroY))[1];
            $gyroZ = unpack('s', pack('S', $gyroZ))[1];

            if ($accelX != 0 || $accelY || $accelZ || $gyroX != 0 || $gyroY != 0 || $gyroZ != 0) {
                $result['success'] = true;
                $result['accelX'] = $accelX / 16384.0;
                $result['accelY'] = $accelY / 16384.0;
                $result['accelZ'] = $accelZ / 16384.0;
                $result['gyroX'] = $gyroX;
                $result['gyroY'] = $gyroY;
                $result['gyroZ'] = $gyroZ;
            }
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo(json_encode($result));
        break;

    case 'test':
        var_dump(unpack('s', pack('S', 65535)));
        break;

    default:
        die("Unsupported command: $cmd");
}

?>