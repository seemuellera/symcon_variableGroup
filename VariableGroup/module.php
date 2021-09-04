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
		$this->RegisterPropertyString("Sender","VariableGroup");
		$this->RegisterPropertyInteger("RefreshInterval",0);
		$this->RegisterPropertyBoolean("DebugOutput",false);
		$this->RegisterPropertyString("AggregationMode","OR");
		$this->RegisterPropertyString("SourceVariables","");
		
		// Variables
		$this->RegisterVariableBoolean("Status","Status","~Switch");
		$this->RegisterVariableString("ResultText","Result Text","~HTMLBox");
		
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
							),
							Array(
								"caption" => "Invert Value",
								"name" => "InvertValue",
								"width" => "50px",
								"edit" => Array("type" => "CheckBox"),
								"add" => false
							)
						)
					);
		
		// Add the buttons for the test center
		$form['actions'][] = Array(	"type" => "Button", "label" => "Refresh", "onClick" => 'VARGROUP_RefreshInformation($id);');

		// Return the completed form
		return json_encode($form);

	}
	
	// Version 1.0
	protected function LogMessage($message, $severity = 'INFO') {
		
		$logMappings = Array();
		// $logMappings['DEBUG'] 	= 10206; Deactivated the normal debug, because it is not active
		$logMappings['DEBUG'] 	= 10201;
		$logMappings['INFO']	= 10201;
		$logMappings['NOTIFY']	= 10203;
		$logMappings['WARN'] 	= 10204;
		$logMappings['CRIT']	= 10205;
		
		if ( ($severity == 'DEBUG') && ($this->ReadPropertyBoolean('DebugOutput') == false )) {
			
			return;
		}
		
		$messageComplete = $severity . " - " . $message;
		parent::LogMessage($messageComplete, $logMappings[$severity]);
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
				$this->LogMessage("Aggregation Mode is not defined","CRIT");
				break;
		}
	}
	
	protected function SetResult($result) {
		
		if (GetValue($this->GetIDForIdent("Result")) != $result) {
			
			SetValue($this->GetIDForIdent("Result"), $result);
		}
	}
	
	protected function SetResultText() {
		
		$sourceVariables = $this->GetSourceVariables();
		
		if (! $sourceVariables) {
			
			$this->LogMessage("Unable to set HTML output as no source variables are defined","CRIT");
			$this->SetResult(false);
			return;
		}
		
		$html = "<table>" .
					"<thead>" .
						"<th>Variable</th>" .
						"<th>Status</th>" .
					"</thead>" .
					"<tbody>";
		
		foreach ($sourceVariables as $currentVariable) {

			$html .= "<tr>" .
						"<td>" . $currentVariable['Name'] . "</td>";
						
			if (GetValue($currentVariable['VariableId']) ) {
				
				$html .= '<td bgcolor="red">Alert</td>';
			}
			else {
				
				$html .= '<td bgcolor="green">OK</td>';
			}
					
			$html .= "</tr>";
		}
					
		$html .= "</tbody></table>";
		
		SetValue($this->GetIDForIdent("ResultText"), $html);
	}
	
	protected function CheckAnd() {
		
		$sourceVariables = $this->GetSourceVariables();
		
		if (! $sourceVariables) {
			
			$this->LogMessage("Unable to check status as no source variables are defined","CRIT");
			$this->SetResult(false);
			return;
		}
		
		$this->SetResultText();
		
		foreach ($sourceVariables as $currentVariable) {
			
			if ($currentVariable['InvertValue']) {
				
				if (GetValue($currentVariable['VariableId']) ) {
					
					$this->SetResult(false);
					return;
				}
			}
			else {
				
				if (! GetValue($currentVariable['VariableId']) ) {
					
					$this->SetResult(false);
					return;
				}
			}
		}
		
		$this->SetResult(true);
		return;
	}
	
	protected function CheckOr() {
		
		$sourceVariables = $this->GetSourceVariables();
		
		if (! $sourceVariables) {
			
			$this->LogMessage("Unable to check status as no source variables are defined","CRIT");
			$this->SetResult(false);
			return;
		}
		
		$this->SetResultText();
		
		foreach ($sourceVariables as $currentVariable) {
			
			if($currentVariable['InvertValue']) {
				
				if (! GetValue($currentVariable['VariableId']) ) {
					
					$this->SetResult(true);
					return;
				}
			}
			else {
				
				if (GetValue($currentVariable['VariableId']) ) {
					
					$this->SetResult(true);
					return;
				}
			}
		}
		
		$this->SetResult(false);
		return;
	}

	public function RequestAction($Ident, $Value) {
	
	
		switch ($Ident) {
		
			case "Status":
				SetValue($this->GetIDForIdent($Ident), $Value);
				if ($Value) {
					
					$this->RefreshInformation();
				}
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
