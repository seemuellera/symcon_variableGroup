<?php

// Klassendefinition
class VariableGroup extends IPSModule {
 
	// Der Konstruktor des Moduls
	// Überschreibt den Standard Kontruktor von IPS
	public function __construct($InstanceID) {
		// Diese Zeile nicht löschen
		parent::__construct($InstanceID);

		// Selbsterstellter Code
	}

	// Überschreibt die interne IPS_Create($id) Funktion
	public function Create() {
		
		// Diese Zeile nicht löschen.
		parent::Create();

		// Properties
		$this->RegisterPropertyString("Sender","DeviceSwitcher");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyString("AggregationMode","OR");
		$this->RegisterPropertyString("SourceVariables","");
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		
		//Actions
		$this->EnableAction("Status");
		
		// Timer
		$this->RegisterTimer("RefreshInformation", 0 , 'VARGROUP_RefreshInformation($_IPS[\'TARGET\']);');
    }

	public function Destroy() {

		// Never delete this line
		parent::Destroy();
	}
 
	// Überschreibt die intere IPS_ApplyChanges($id) Funktion
	public function ApplyChanges() {

		$newInterval = $this->ReadPropertyInteger("RefreshInterval") * 1000;
		$this->SetTimerInterval("RefreshInformation", $newInterval);
		
		$sourceVariables = $this->GetSourceVariables();
		
		if ($sourceVariables) {
			
			foreach ($sourceVariables as $currentVariable) {
				
				$this->RegisterMessage($currentVariable['VariableId'], VM_UPDATE);
			}
		}
		
		switch ($this->ReadPropertyString("AggregationMode") ) {
			
			case "AND":
				$resultType = 0;
				$resultProfile = "~Alert";
				break;
			case "OR":
				$resultType = 0;
				$resultProfile = "~Alert";
				break;
			default:
				$resultType = 0;
				$resultProfile = "~Alert";
				break;
		}
		
		$this->MaintainVariable("Result","Result",$resultType,$resultProfile,0,true);
			
		// Diese Zeile nicht löschen
		parent::ApplyChanges();
	}


	public function GetConfigurationForm() {
        	
		// Initialize the form
		$form = Array(
            		"elements" => Array(),
					"actions" => Array()
        		);

		// Add the Elements
		$form['elements'][] = Array("type" => "NumberSpinner", "name" => "RefreshInterval", "caption" => "Refresh Interval");
		$form['elements'][] = Array("type" => "CheckBox", "name" => "DebugOutput", "caption" => "Enable Debug Output");
		$form['elements'][] = Array(
						"type" => "Select", 
						"name" => "AggregationMode", 
						"caption" => "Select Aggregation Mode",
						"options" => Array(
							Array(
								"caption" => "AND - Compares multiple boolean variables -> Result is true if ALL are true",
								"value" => "AND"
							),
							Array(
								"caption" => "OR - Compares multiple boolean variables -> Result is true if any is true",
								"value" => "OR"
							)
						)
					);
		$form['elements'][] = Array(
						"type" => "List", 
						"name" => "SourceVariables", 
						"caption" => "Source Variables",
						"rowCount" => 10,
						"add" => true,
						"delete" => true,
						"columns" => Array(
							Array(
								"caption" => "Variable Id",
								"name" => "VariableId",
								"width" => "350px",
								"edit" => Array("type" => "SelectVariable"),
								"add" => 0
							),
							Array(
								"caption" => "Name",
								"name" => "Name",
								"width" => "250px",
								"edit" => Array("type" => "ValidationTextBox"),
								"add" => "Display Name"
							)
						)
					);
		
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'VARGROUP_RefreshInformation($id);');

		// Return the completed form
		return json_encode($form);

	}
	
	protected function LogMessage($message, $severity = 'INFO') {
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		
		IPS_LogMessage($this->ReadPropertyString('Sender') . " - " . $this->InstanceID, $messageComplete);
	}

	public function RefreshInformation() {

		$this->LogMessage("Refresh in Progress", "DEBUG");
		
		if (! GetValue($this->GetIDForIdent("Status")) ) {
			
			$this->LogMessage("Device will not be checked because checking is deactivated","DEBUG");
		}
		
		switch ($this->ReadPropertyString("AggregationMode")) {
			
			case "AND":
				$this->CheckAnd();
				break;
			case "OR":
				$this->CheckOr();
				break;
			default:
				$this->LogMessage("Aggregation Mode is not defined","ERROR");
				break;
		}
	}
	
	protected function SetResult($result) {
		
		if (GetValue($this->GetIDForIdent("Result")) != $result) {
			
			SetValue($this->GetIDForIdent("Result"), $result);
		}
	}
	
	protected function CheckAnd() {
		
		$sourceVariables = $this->GetSourceVariables();
		
		if (! $sourceVariables) {
			
			$this->LogMessage("Unable to check status as no source variables are defined","ERROR");
			$this->SetResult(false);
			return;
		}
		
		foreach ($sourceVariables as $currentVariable) {
			
			if (! GetValue($currentVariable['VariableId']) ) {
				
				$this->SetResult(false);
				return;
			}
		}
		
		$this->SetResult(true);
		return;
	}
	
	protected function CheckOr() {
		
		$sourceVariables = $this->GetSourceVariables();
		
		if (! $sourceVariables) {
			
			$this->LogMessage("Unable to check status as no source variables are defined","ERROR");
			$this->SetResult(false);
			return;
		}
		
		foreach ($sourceVariables as $currentVariable) {
			
			if (GetValue($currentVariable['VariableId']) ) {
				
				$this->SetResult(true);
				return;
			}
		}
		
		$this->SetResult(false);
		return;
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				SetValue($this->GetIDForIdent($Ident), $Value);
				break;
			default:
				throw new Exception("Invalid Ident");
		}
	}
	
	public function MessageSink($TimeStamp, $SenderId, $Message, $Data) {
	
		$this->LogMessage("$TimeStamp - $SenderId - $Message - " . implode(" ; ",$Data), "DEBUG");
		
		$this->RefreshInformation();
	}
	
	protected function GetSourceVariables() {
		
		$sourceVariablesJson = $this->ReadPropertyString("SourceVariables");
		$sourceVariables = json_decode($sourceVariablesJson, true);
		
		if (is_array($sourceVariables)) {
			
			if (count($sourceVariables) != 0) {
				
				return $sourceVariables;
			}
			else {
				
				return false;
			}
		}
		else {
			
			return false;
		}
	}
}
