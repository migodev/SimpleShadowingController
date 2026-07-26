<?php

declare(strict_types=1);

class SimpleShadowingController extends IPSModule {
    
    public function Create() {
        parent::Create();
        
        //Properties
        $this->RegisterPropertyString('ShutterVariables', '[]');
        $this->RegisterPropertyInteger('AzimutFrom', 0);
        $this->RegisterPropertyInteger('AzimutTo', 0);
        $this->RegisterPropertyInteger('AzimutId', 0);
        $this->RegisterPropertyInteger('BrightnessId', 0);

        $this->RegisterPropertyInteger('ShadowingPercent', 0);
        $this->RegisterPropertyInteger('OpenPercent', 0);
        $this->RegisterPropertyInteger('IgnoreShutterPercent', 100);
        $this->RegisterPropertyInteger('MoveMode', 0);

        $this->RegisterPropertyInteger('InputTemperatureCurrentVariable', 0);
        $this->RegisterPropertyInteger('InputTemperatureTargetVariable', 0);
        $this->RegisterPropertyInteger('GlobalShadowingStatusVariable', 0);
        $this->RegisterPropertyInteger('GlobalShutterControlVariable', 0);

        $this->RegisterPropertyFloat('ThresholdTemperature', 10);
        $this->RegisterPropertyInteger('InputOutdoorTemperature', 0);

        $this->RegisterAttributeInteger('LastExecute', 0);

        //Variables
        $ActiveOptions = json_encode([
            [
                'Value' => true,
                'Caption' => 'Automatik',
                'IconActive' => false,
                'Icon' => '',
                'Color' => 0x00ff00
            ],[
                'Value' => false,
                'Caption' => 'Deaktiviert',
                'IconActive' => false,
                'Icon' => '',
                'Color' => 0xff0000
            ]
        ]);    
        $this->RegisterVariableBoolean('Active', 'Raum Beschattung aktiv', ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'power-off', 'OPTIONS' => $ActiveOptions], 1);
        $this->EnableAction('Active');

        $this->RegisterVariableBoolean('ColdShadowing', 'Beschattung bei Kälte', ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'snowflake', 'OPTIONS' => $ActiveOptions], 2);
        $this->EnableAction('ColdShadowing');

        $this->RegisterVariableBoolean('EvaluationIndoorTemperature', 'Auswertung Innentemperaturen', ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'temperature-high', 'OPTIONS' => $ActiveOptions], 3);
        $this->EnableAction('EvaluationIndoorTemperature');

        $this->RegisterVariableInteger('PauseBetweenMovements', 'Pause zwischen 2 Bewegungen', ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_INPUT, 'ICON' => 'circle-pause', "SUFFIX" => " Minuten"], 4);
        $this->EnableAction('PauseBetweenMovements');
        
        $this->RegisterVariableInteger("tresholdBrightness", 'Grenzwert Helligkeit', [
            "PRESENTATION" => VARIABLE_PRESENTATION_SLIDER,
            "MIN" => 1000,
            "MAX" => 80000,
            "STEP_SIZE" => 1000,
            "USAGE_TYPE" => 5,
            "GRADIENT_TYPE" => 0, 
            "SUFFIX" => " Lux", 
            "ICON" => "brightness"
        ], 5);
        $this->EnableAction('tresholdBrightness');

        $this->RegisterVariableBoolean('StatusShadowing', 'Status', ['PRESENTATION' => VARIABLE_PRESENTATION_VALUE_PRESENTATION, 'ICON' => 'shutters'], 6);
    }
    
    public function ApplyChanges() {
        parent::ApplyChanges();
        
        //Unregister all messages
        $messageList = array_keys($this->GetMessageList());
        foreach ($messageList as $message) {
            $this->UnregisterMessage($message, VM_UPDATE);
        }
        
        //Delete all references in order to read them
        foreach ($this->GetReferenceList() as $referenceID) {
            $this->UnregisterReference($referenceID);
        }
        
        if ($this->ReadPropertyInteger("GlobalShadowingStatusVariable") > 0) {
            $this->RegisterMessage($this->ReadPropertyInteger("GlobalShadowingStatusVariable"), VM_UPDATE);
            $this->RegisterReference($this->ReadPropertyInteger("GlobalShadowingStatusVariable"));
        }
        if ($this->ReadPropertyInteger("GlobalShutterControlVariable") > 0) {
            $this->RegisterReference($this->ReadPropertyInteger("GlobalShutterControlVariable"));
        }
        if ($this->ReadPropertyInteger("InputTemperatureCurrentVariable") > 0) {
            $this->RegisterMessage($this->ReadPropertyInteger("InputTemperatureCurrentVariable"), VM_UPDATE);
            $this->RegisterReference($this->ReadPropertyInteger("InputTemperatureCurrentVariable"));
        }
        if ($this->ReadPropertyInteger("InputOutdoorTemperature") > 0) {
            $this->RegisterMessage($this->ReadPropertyInteger("InputOutdoorTemperature"), VM_UPDATE);
            $this->RegisterReference($this->ReadPropertyInteger("InputOutdoorTemperature"));
        }
        if ($this->ReadPropertyInteger("InputTemperatureTargetVariable") > 0) {
            $this->RegisterReference($this->ReadPropertyInteger("InputTemperatureTargetVariable"));
        }
        if ($this->ReadPropertyInteger("AzimutId") > 0) {
            $this->RegisterMessage($this->ReadPropertyInteger("AzimutId"), VM_UPDATE);
            $this->RegisterReference($this->ReadPropertyInteger("AzimutId"));
        }
        if ($this->ReadPropertyInteger("BrightnessId") > 0) {
            $this->RegisterMessage($this->ReadPropertyInteger("BrightnessId"), VM_UPDATE);
            $this->RegisterReference($this->ReadPropertyInteger("BrightnessId"));
        }
        
        $shutterVariables = json_decode($this->ReadPropertyString('ShutterVariables'), true);
        foreach ($shutterVariables as $shutterVariable) {
            $shutterID = $shutterVariable['VariableID'];
            $this->RegisterReference($shutterID);
        }

        $shadowing  = $this->ReadPropertyInteger('ShadowingPercent');
        $opening    = $this->ReadPropertyInteger('OpenPercent');
        $ignore     = $this->ReadPropertyInteger('IgnoreShutterPercent');

        if (($shadowing >= $ignore) || ($opening >= $ignore)) {
            $this->SetStatus(201); // eigener Fehlerstatus
            trigger_error(
                'Beschattungs/Öffnungs-Prozent muss kleiner als der Ignorieren-Prozent Wert sein. Beschattung deaktiviert',
                E_USER_WARNING
            );
            $this->SetValue('Active', false);
            return;
        }

        $this->SetStatus(IS_ACTIVE);
    }
    
    public function GetConfigurationForm() {
        //Add options to form
        $jsonForm = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
                
        //Set status column for outputs
        $outputVariables = json_decode($this->ReadPropertyString('ShutterVariables'), true);
        foreach ($outputVariables as $outputVariable) {
            $jsonForm['elements'][1]['values'][] = [
                'Status' => $this->GetShutterVariableStatus($outputVariable['VariableID'])
            ];
        }

        if ($this->GetStatus() === 201) {
            foreach ($jsonForm['elements'] as &$element) {
                if (isset($element['name']) && $element['name'] == 'ErrorMessageIgnoreShutterPercent') {
                    $element['visible'] = true;
                    $element['caption'] = 'OpenPercent muss kleiner als IgnoreShutterPercent sein.';
                }
            }
        }

        // Show Delay Off Control if activated
        $jsonForm['elements'][2]['expanded'] = true;

        return json_encode($jsonForm);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
        //https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/messages/
        if ($Message == VM_UPDATE) {          
            $result = $this->validateShadowing($Data[0], $SenderID);
            $this->executeShadowing($result);
        }
    }
    
    public function SetActive(bool $Value) {
        if ($this->GetValue('Active') !== $Value) {
            $this->SetValue('Active', $Value);

            if ($Value === false) {
                $this->executeShadowing(false);
            }
        }
    }

    public function SetColdShadowing(bool $Value) {
        if ($this->GetValue('ColdShadowing') !== $Value) {
            $this->SetValue('ColdShadowing', $Value);
        }
    }

    public function SetEvaluationIndoorTemperature(bool $Value) {
        if ($this->GetValue('EvaluationIndoorTemperature') !== $Value) {
            $this->SetValue('EvaluationIndoorTemperature', $Value);
        }
    }

    public function SetPauseBetweenMovements(int $Value) {
        if ($this->GetValue('PauseBetweenMovements') !== $Value) {
            $this->SetValue('PauseBetweenMovements', $Value);
            $this->resetPause();
        }
    }

    public function SetTresholdBrightness(int $Value) {
        if ($this->GetValue('tresholdBrightness') !== $Value) {
            $this->SetValue('tresholdBrightness', $Value);
        }
    }

    public function resetPause() {
        $this->WriteAttributeInteger('LastExecute', 0);
        $this->SendDebug('LastExecute', "Reset LastExecute", 0);
    }

    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case 'Active':
                $this->SetActive($Value);
                break;
            case 'ColdShadowing':
                $this->SetColdShadowing($Value);
                break;
            case 'EvaluationIndoorTemperature':
                $this->SetEvaluationIndoorTemperature($Value);
                break;
            case 'PauseBetweenMovements':
                $this->SetPauseBetweenMovements($Value);
                break;
            case 'tresholdBrightness':
                $this->SetTresholdBrightness($Value);
                break;
            default:
                throw new Exception('Invalid ident');
        }
    }

    private function GetShutterVariableStatus($outputID) {
        if (!IPS_VariableExists($outputID)) {
            return 'Missing';
        } else {
            switch (IPS_GetVariable($outputID)['VariableType']) {
                case VARIABLETYPE_INTEGER:
                    return 'OK';
                default:
                    return 'Int required';
            }
        }
    }

    private function checkAndSetPause() {
        $lastExecute = $this->ReadAttributeInteger('LastExecute');
        if ($lastExecute === 0) {
            // not set, so we set time
            $this->WriteAttributeInteger('LastExecute', time());
            $this->SendDebug('execute', "LastExecute set", 0);
            return true;
        } else {
            $diffExecute = $this->GetValue('PauseBetweenMovements') * 60;
            if (($lastExecute + $diffExecute) < time()) {
                // Set new Execute Time
                $this->WriteAttributeInteger('LastExecute', time());
                $this->SendDebug('execute', "LastExecute exceeded (".date("H:i:s", $lastExecute)." - ".date("H:i:s", $lastExecute + $diffExecute)."), continue", 0);
                return true;
            } else {
                $ddiff = ($lastExecute + $diffExecute)-time();
                $this->SendDebug('execute', "LastExecute not exceeded, diff: ".$ddiff." seconds", 0);
                return false;
            }
        }
    }

    private function executeShadowing($doShadowing) {
        $this->SendDebug('execute', "Calling executeShadowing with Param: ".json_encode($doShadowing), 0);

        if (($this->GetValue('Active') !== true) && ($doShadowing === true)) {
            $this->SendDebug('execute', "Shadowing not active, exit", 0);
            return false;
        }
        
        if ($this->ReadPropertyInteger("GlobalShutterControlVariable") > 0) {                
            if (GetValue($this->ReadPropertyInteger('GlobalShutterControlVariable')) === false) {
                $this->SendDebug('execute', "Global Shutter Control not active, exit", 0);
                return false;
            }
        } else {
                $this->SendDebug('execute', "Global Shutter Control Variable not set, continue executing", 0);
        }

        $shadowingPercent   = $this->ReadPropertyInteger("ShadowingPercent");
        $openPercent        = $this->ReadPropertyInteger("OpenPercent");

        // If validation returns false and Shadowing Status is true, then we do Not Shadowing
        $currentStatus = $this->GetValue('StatusShadowing');
        $this->SendDebug('StatusShadowing', json_encode($currentStatus), 0);
        if (($doShadowing === false) && ($currentStatus === true)) {
            // Open & Exit Shadowing
            $doShadowing = false;
            $this->SendDebug('execute', "Shadowing currently active, disabled by validation", 0);
        }

        $shutterVariables = json_decode($this->ReadPropertyString('ShutterVariables'), true);
        foreach ($shutterVariables as $shutter) {
            $shutterId = $shutter['VariableID'];

            // skip 100% shutter
            if (GetValue($shutterId) >= $this->ReadPropertyInteger('IgnoreShutterPercent')) {
                $this->SendDebug('execute', "Skip Shutter with ID ".$shutterId." because its closed: greater (".GetValue($shutterId)."%) than ".$this->ReadPropertyInteger('IgnoreShutterPercent')."%", 0);
                continue;
            }
            
            if (($doShadowing === true) && ($currentStatus === false)) {
                //Keep Pause for defined minutes
                if ($this->checkAndSetPause() === false) { return false; }

                if (HasAction($shutterId)) {
                    RequestAction($shutterId, $shadowingPercent);
                } else {
                    SetValue($shutterId, $shadowingPercent);
                }
                $this->SetValue('StatusShadowing', true);
                $this->SendDebug('execute', "Do Shadowing on ".$shutterId." and move to percent: ".$shadowingPercent, 0);
            } elseif (($doShadowing === false) && ($currentStatus === true)) {
                //Keep Pause for defined minutes
                if ($this->checkAndSetPause() === false) { return false; }

                if ($this->ReadPropertyInteger('MoveMode') !== 1) {
                    if (HasAction($shutterId)) {
                        RequestAction($shutterId, $openPercent);
                    } else {
                        SetValue($shutterId, $openPercent);
                    }
                    $this->SetValue('StatusShadowing', false);
                    $this->SendDebug('execute', "End Shadowing on ".$shutterId." and move to percent: ".$openPercent, 0);
                } else {
                    $this->SetValue('StatusShadowing', false);
                    $this->SendDebug('execute', "End Shadowing on ".$shutterId.", because of MoveMode 1 we do not open - But Shadowing is on exit", 0);
                }
            } else {
                $this->SendDebug('execute', "Nothing Todo - Shutters are already moved", 0);
            }
        }
    }

    private function validateShadowing($data, $senderId) {
        //Exit if global shadowing is disabled
        $globalShadowingStatus = GetValue($this->ReadPropertyInteger('GlobalShadowingStatusVariable'));
        $this->SendDebug('globalShadowingStatus', json_encode($globalShadowingStatus), 0);
        $this->SendDebug('EvaluationIndoorTemperature', json_encode($this->GetValue('EvaluationIndoorTemperature')), 0);

        if ($senderId == $this->ReadPropertyInteger('GlobalShadowingStatusVariable')) {
            if (($globalShadowingStatus === true) && ($this->GetValue('EvaluationIndoorTemperature') === false)) {
                // Activate only if global Status = true and RoomControl = false, otherwise enablement is controlled via Temperature Rule
                $this->SendDebug('status', "enabled by globalShadowingStatus and EvaluationIndoorTemperature = false", 0);
                $this->SetActive(true);
                return true;
            }
        }

        if ($globalShadowingStatus === false) {
            $this->SetActive(false);
            $this->SendDebug('status', "disabled by globalShadowingStatus", 0);
            return false;
        }

        // Check Outdoor Temperature
        if (($this->GetValue('ColdShadowing') === false) && ($this->ReadPropertyInteger('InputOutdoorTemperature') > 1)) {
            $outdoorTemp = floatval(GetValue($this->ReadPropertyInteger('InputOutdoorTemperature')));
            $threshold = $this->ReadPropertyFloat('ThresholdTemperature');
            if ($outdoorTemp < $threshold) {
                $this->SetActive(false);
                $this->SendDebug('status', "disabled by ColdShadowing", 0);
                return false;
            }
        }

        // if we want indoor temperature based shadowing
        if ($this->GetValue('EvaluationIndoorTemperature') === true) {
            if (($this->ReadPropertyInteger('InputTemperatureCurrentVariable') <= 1) || ($this->ReadPropertyInteger('InputTemperatureTargetVariable') <= 1)) {
                $this->SendDebug('validation', "input indoor Tempereratures are not set", 0);
                return false;
            } else {
                $curTemp = floatval(GetValue($this->ReadPropertyInteger('InputTemperatureCurrentVariable')));
                $tarTemp = floatval(GetValue($this->ReadPropertyInteger('InputTemperatureTargetVariable')));
                
                if ($curTemp >= $tarTemp) {
                    $this->SetActive(true);
                    $this->SendDebug('status', "enabled by Indoor Temperature", 0);
                    return true;
                } elseif ($curTemp < $tarTemp) {
                    $this->SetActive(false);
                    $this->SendDebug('status', "disabled by Indoor Temperature", 0);
                    return false;
                }
            }
        }else {
            $this->SendDebug('validation', "EvaluationIndoorTemperature is false", 0);
        }
        
        // Validate Azimut
        $azimut = GetValue($this->ReadPropertyInteger('AzimutId'));
        $azifr = $this->ReadPropertyInteger('AzimutFrom');
        $azito = $this->ReadPropertyInteger('AzimutTo');

        $azimutCheck = false;
        $brightnessCheck = false;

        if ($azifr > $azito) {
            // eg. 270 - 60
           if (($azimut > $azifr) || ($azimut < $azito)) {
                $this->SendDebug('validation', "Azimut (".round($azimut, 1).") in Range: ".$azifr." - ".$azito, 0);
                $azimutCheck = true;
            } else {
                $this->SendDebug('validation', "Azimut (".round($azimut, 1).") not in Range: ".$azifr." - ".$azito, 0);
                $azimutCheck = false;
            }
        } else {
            // eg. 90 - 180
            if (($azimut >= $azifr) && ($azimut <= $azito)) {
                $this->SendDebug('validation', "Azimut (".round($azimut, 1).") in Range: ".$azifr." - ".$azito, 0);
                $azimutCheck = true;
            } else {
                $this->SendDebug('validation', "Azimut (".round($azimut, 1).") not in Range: ".$azifr." - ".$azito, 0);
                $azimutCheck = false;
            }
        }
        
        // Check Brightness
        $brightness = GetValue($this->ReadPropertyInteger('BrightnessId'));
        $brightnessTreshold = $this->GetValue('tresholdBrightness');
        
        if ($brightness >= $brightnessTreshold) {
            $this->SendDebug('validation', "Brightness (".$brightness.") is above treshold: ".$brightnessTreshold, 0);
            $brightnessCheck = true;
        } else {
            $this->SendDebug('validation', "Brightness (".$brightness.") is below treshold: ".$brightnessTreshold, 0);
            $brightnessCheck = false;
        }

        // if both azimut and brightness are OK, then return true
        if (($azimutCheck === true) && ($brightnessCheck === true)) {
            return true;
        } else {
            return false;
        }
    }

    public function ImportVariables() {
        $validDirections = array("Ost", "West", "Süd");
        $selfNameArray = explode(" ", IPS_GetName($this->InstanceID));
        $direction = end($selfNameArray); // Naming like Beschattungssteuerung Ost
        if (!in_array($direction, $validDirections)) {
            $direction = false;
        }

        // find General Category & identify Bool Variable for global shadowing
        $foundGeneralCategory = false;
        $cat = IPS_GetCategoryList();
        foreach ($cat as $c) {
            $catInfo = IPS_GetObject($c);
            if ($catInfo['ObjectName'] == 'Beschattung') {
                $pc = IPS_GetObject(IPS_GetParent($catInfo['ObjectID']));
                if ($pc['ObjectName'] == "Allgemein") {
                    $foundGeneralCategory = true;
                    $ShadowingCatId = $catInfo['ObjectID'];
                    break;
                }
            }
        }
        $varBeschattungsID = @IPS_GetVariableIDByName('Aktivierung globale Beschattung', $ShadowingCatId);
        if ($varBeschattungsID === false) {
            // Creates Category & Global Variable
            if ($foundGeneralCategory === false) {
                $CatID = IPS_CreateCategory();
                IPS_SetName($CatID, "Allgemein");
                IPS_SetParent($CatID, 0);

                $CatID_BS = IPS_CreateCategory();
                IPS_SetName($CatID_BS, "Beschattung");
                IPS_SetParent($CatID_BS, $CatID);
            } else {
                $CatID_BS = $ShadowingCatId; // Category already exists, only Variable is missing
                $this->SendDebug('import', "Category already exists, only Variable was missing", 0);
            }
            //Create Bool Variable
            $varBeschattungsID = IPS_CreateVariable(0);
            IPS_SetName($varBeschattungsID, "Aktivierung globale Beschattung");
            IPS_SetVariableCustomPresentation($varBeschattungsID, ['PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION, 'ICON' => 'power-off']);
            IPS_SetParent($varBeschattungsID, $CatID_BS);
            
            //Create Default Action Script
            $ScriptID = IPS_CreateScript(0);
            IPS_SetName($ScriptID, "Aktionsskript");
            IPS_SetScriptContent($ScriptID, "<?php SetValue(\$_IPS['VARIABLE'], \$_IPS['VALUE']); ?>");
            IPS_SetParent($ScriptID, $varBeschattungsID);
            IPS_SetVariableCustomAction($varBeschattungsID, $ScriptID);
        }
        $this->UpdateFormField('GlobalShadowingStatusVariable', 'value', $varBeschattungsID);
        
        // Treshold Brightness
        /*if ($direction !== false) {
            $tresholdId = IPS_FindObjectIDByName("Grenzwert Helligkeit Beschattung ".$direction, IPS_GetParent($varBeschattungsID));
            $this->UpdateFormField('tresholdBrightnessId', 'value', $tresholdId);
        }*/


        // find General Category & identify Bool Variable for global shutter control
        $foundGeneralCategory = false;
        $cat = IPS_GetCategoryList();
        foreach ($cat as $c) {
            $catInfo = IPS_GetObject($c);
            if ($catInfo['ObjectName'] == 'Nachtabsenkung') {
                $pc = IPS_GetObject(IPS_GetParent($catInfo['ObjectID']));
                if ($pc['ObjectName'] == "Allgemein") {
                    $foundGeneralCategory = true;
                    $nightShutterCatId = $catInfo['ObjectID'];
                    break;
                }
            }
        }
        $varNachtabsenkungID = @IPS_GetVariableIDByName('Automatische Rollladensteuerung', $nightShutterCatId);
        if ($varNachtabsenkungID !== false) {
            $this->UpdateFormField('GlobalShutterControlVariable', 'value', $varNachtabsenkungID);
        }


        // find Eltako Weatherstation
        $ch = IPS_GetInstanceListByModuleID('{9E4572C0-C306-4F00-B536-E75B4950F094}');
        if (count($ch) > 0) {
            $childs = IPS_GetChildrenIDs($ch[0]);
            foreach ($childs as $child) {
                $name = IPS_GetName($child);
                if (preg_match("/Temperatur AVG/i", $name)) {
                    $this->UpdateFormField('InputOutdoorTemperature', 'value', $child);
                    break;
                } elseif ($name == "Temperatur") {
                    // Fallback if AVG Variable is not present
                    $this->UpdateFormField('InputOutdoorTemperature', 'value', $child);
                }
            }
            
            //Brightness Variable
            if ($direction !== false) {
                foreach ($childs as $child) {
                    $name = IPS_GetName($child);
                    if (preg_match("/Helligkeit \(".$direction."\)/i", $name)) {
                        $this->UpdateFormField('BrightnessId', 'value', $child);
                        break;
                    }
                }
            }
        }

        // Azimut
        // Find all instances of the Location Control module
        $locationInstances = IPS_GetInstanceListByModuleID('{45E97A63-F870-408A-B259-2933F7EABF74}');
        if ($locationInstances) {
            $locationID = $locationInstances[0]; // Gets the first matching instance ID
            $azimutId = IPS_FindObjectIDByName("Azimut", $locationID);
            $this->UpdateFormField('AzimutId', 'value', $azimutId);
        }
    }

}
?>