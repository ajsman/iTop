<?php
// Copyright (C) 2015-2016 Combodo SARL
//
//   This file is part of iTop.
//
//   iTop is free software; you can redistribute it and/or modify
//   it under the terms of the GNU Affero General Public License as published by
//   the Free Software Foundation, either version 3 of the License, or
//   (at your option) any later version.
//
//   iTop is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU Affero General Public License for more details.
//
//   You should have received a copy of the GNU Affero General Public License
//   along with iTop. If not, see <http://www.gnu.org/licenses/>

/**
 * Bulk export: Excel (xlsx) export
 *
 * @copyright   Copyright (C) 2015-2016 Combodo SARL
 * @license     http://opensource.org/licenses/AGPL-3.0
 */

require_once(APPROOT.'application/xlsxwriter.class.php');

class ExcelBulkExport extends TabularBulkExport
{
	protected $sData;

	public function __construct()
	{
		parent::__construct();
		$this->aStatusInfo['status'] = 'not_started';
		$this->aStatusInfo['position'] = 0;
	}

	public function Cleanup()
	{
		@unlink($this->aStatusInfo['tmp_file']);
		parent::Cleanup();
	}

	public function DisplayUsage(Page $oP)
	{
		$oP->p(" * xlsx format options:");
		$oP->p(" *\tfields: the comma separated list of field codes to export (e.g: name,org_id,service_name...).");
		$oP->p(" *\tformatted_text: set to 1 to export case logs and formatted text fields with their HTML markup. Default is 0 (= plain text)");
		$oP->p(" *\tdate_format: the format to use when exporting date and time fields (default = the SQL format). e.g. 'Y-m-d H:i:s'");
	}

	public function ReadParameters()
	{
		parent::ReadParameters();
		$this->aStatusInfo['formatted_text'] = (bool)utils::ReadParam('formatted_text', 0, true);
			
		$sDateFormatRadio = utils::ReadParam('excel_date_format_radio', '');
		switch($sDateFormatRadio)
		{
			case 'default':
			// Export from the UI => format = same as is the UI
			$this->aStatusInfo['date_format'] = (string)AttributeDateTime::GetFormat();
			break;
			
			case 'custom':
			// Custom format specified from the UI
			$this->aStatusInfo['date_format'] = utils::ReadParam('date_format', (string)AttributeDateTime::GetFormat(), true, 'raw_data');
			break;
			
			default:
			// Export from the command line (or scripted) => default format is SQL, as in previous versions of iTop, unless specified otherwise
			$this->aStatusInfo['date_format'] = utils::ReadParam('date_format', (string)AttributeDateTime::GetSQLFormat(), true, 'raw_data');
		}
	}
	
	public function EnumFormParts()
	{
		return array_merge(parent::EnumFormParts(), array('xlsx_options' => array('formatted_text') ,'interactive_fields_xlsx' => array('interactive_fields_xlsx')));
	}

	public function DisplayFormPart(WebPage $oP, $sPartId)
	{
		switch($sPartId)
		{
			case 'interactive_fields_xlsx':
				$this->GetInteractiveFieldsWidget($oP, 'interactive_fields_xlsx');
				break;
					
			case 'xlsx_options':
				$oP->add('<fieldset><legend>'.Dict::S('Core:BulkExport:XLSXOptions').'</legend>');
				$oP->add('<table class="export_parameters"><tr><td style="vertical-align:top">');
				
				$sChecked = (utils::ReadParam('formatted_text', 0) == 1) ? ' checked ' : '';
				$oP->add('<h3>'.Dict::S('Core:BulkExport:TextFormat').'</h3>');
				$oP->add('<input type="checkbox" id="xlsx_formatted_text" name="formatted_text" value="1"'.$sChecked.'><label for="xlsx_formatted_text"> '.Dict::S('Core:BulkExport:OptionFormattedText').'</label>');
				
				$oP->add('</td><td style="vertical-align:top">');
				
				$sDateTimeFormat = utils::ReadParam('date_format', (string)AttributeDateTime::GetFormat(), true, 'raw_data');
				$sDefaultChecked = ($sDateTimeFormat == (string)AttributeDateTime::GetFormat()) ? ' checked' : '';
				$sCustomChecked = ($sDateTimeFormat !== (string)AttributeDateTime::GetFormat()) ? ' checked' : '';
				$oP->add('<h3>'.Dict::S('Core:BulkExport:DateTimeFormat').'</h3>');
				$sDefaultFormat = htmlentities((string)AttributeDateTime::GetFormat(), ENT_QUOTES, 'UTF-8');
				$sExample = htmlentities(date((string)AttributeDateTime::GetFormat()), ENT_QUOTES, 'UTF-8');
				$oP->add('<input type="radio" id="excel_date_time_format_default" name="excel_date_format_radio" value="default"'.$sDefaultChecked.'><label for="excel_date_time_format_default"> '.Dict::Format('Core:BulkExport:DateTimeFormatDefault_Example', $sDefaultFormat, $sExample).'</label><br/>');
				$sFormatInput = '<input type="text" size="15" name="date_format" id="excel_custom_date_time_format" title="" value="'.htmlentities($sDateTimeFormat, ENT_QUOTES, 'UTF-8').'"/>';
				$oP->add('<input type="radio" id="excel_date_time_format_custom" name="excel_date_format_radio" value="custom"'.$sCustomChecked.'><label for="excel_date_time_format_custom"> '.Dict::Format('Core:BulkExport:DateTimeFormatCustom_Format', $sFormatInput).'</label>');
				
				$oP->add('</td></tr></table>');
				
				$oP->add('</fieldset>');
				$sJSTooltip = json_encode('<div class="date_format_tooltip">'.Dict::S('UI:CSVImport:CustomDateTimeFormatTooltip').'</div>');
				$oP->add_ready_script(
<<<EOF
$('#excel_custom_date_time_format').tooltip({content: function() { return $sJSTooltip; } });
$('#form_part_xlsx_options').on('preview_updated', function() { FormatDatesInPreview('excel', 'xlsx'); });
$('#excel_date_time_format_default').on('click', function() { FormatDatesInPreview('excel', 'xlsx'); });
$('#excel_date_time_format_custom').on('click', function() { FormatDatesInPreview('excel', 'xlsx'); });
$('#excel_custom_date_time_format').on('click', function() { $('#excel_date_time_format_custom').prop('checked', true); FormatDatesInPreview('excel', 'xlsx'); }).on('keyup', function() { FormatDatesInPreview('excel', 'xlsx'); });					
EOF
				);
				break;

				default:
				return parent:: DisplayFormPart($oP, $sPartId);
		}
	}

	protected function SuggestField($sClass, $sAttCode)
	{
		switch($sAttCode)
		{
			case 'id': // replace 'id' by 'friendlyname'
				$sAttCode = 'friendlyname';
				break;
					
			default:
				$oAttDef = MetaModel::GetAttributeDef($sClass, $sAttCode);
				if ($oAttDef instanceof AttributeExternalKey)
				{
					$sAttCode .= '_friendlyname';
				}
		}

		return parent::SuggestField($sClass, $sAttCode);
	}

	protected function GetSampleData($oObj, $sAttCode)
	{
		if ($sAttCode != 'id')
		{
			$oAttDef = MetaModel::GetAttributeDef(get_class($oObj), $sAttCode);
			if ($oAttDef instanceof AttributeDateTime) // AttributeDate is derived from AttributeDateTime
			{
				$sClass = (get_class($oAttDef) == 'AttributeDateTime') ? 'user-formatted-date-time' : 'user-formatted-date';
				return '<div class="'.$sClass.'" data-date="'.$oObj->Get($sAttCode).'">'.htmlentities($oAttDef->GetEditValue($oObj->Get($sAttCode), $oObj), ENT_QUOTES, 'UTF-8').'</div>';
			}
		}
		return '<div class="text-preview">'.htmlentities($this->GetValue($oObj, $sAttCode), ENT_QUOTES, 'UTF-8').'</div>';
	}

	protected function GetValue($oObj, $sAttCode)
	{
		switch($sAttCode)
		{
			case 'id':
				$sRet = $oObj->GetKey();
				break;
					
			default:
			$value = $oObj->Get($sAttCode);
			if ($value instanceOf ormCaseLog)
			{
				 if (array_key_exists('formatted_text', $this->aStatusInfo) && $this->aStatusInfo['formatted_text'])
				 {
				 	$sText = $value->GetText();
				 }
				 else
				 {
				 	$sText = $value->GetAsPlainText();
				 }
				// Extract the case log as text and remove the "===" which make Excel think that the cell contains a formula the next time you edit it!
				$sRet = trim(preg_replace('/========== ([^=]+) ============/', '********** $1 ************', $sText));
			}
			else if ($value instanceOf DBObjectSet)
			{
				$oAttDef = MetaModel::GetAttributeDef(get_class($oObj), $sAttCode);
				$sRet = $oAttDef->GetAsCSV($value, '', '', $oObj);
			}
			else if ($value instanceOf ormCustomFieldsValue)
			{
				$oAttDef = MetaModel::GetAttributeDef(get_class($oObj), $sAttCode);
				$sRet = $oAttDef->GetAsCSV($value, "\n", '', $oObj);
			}
			else
			{
				$oAttDef = MetaModel::GetAttributeDef(get_class($oObj), $sAttCode);
				if ($oAttDef instanceof AttributeDateTime)
				{
					// Date and times are formatted using the ISO encoding, not the localized format
					if ($oAttDef->IsNull($value))
					{
						// NOt a valid date
						$sRet = '';
					}
					else
					{
						$sRet = $value;
					}
				}
				else if (array_key_exists('formatted_text', $this->aStatusInfo) && $this->aStatusInfo['formatted_text'])
				{
					$sRet = $oAttDef->GetEditValue($value, $oObj);
				}
				else
				{
					$sRet = $oAttDef->GetAsPlainText($value, $oObj);
				}
			}
		}
		return $sRet;
	}

	public function GetHeader()
	{
		$oSet = new DBObjectSet($this->oSearch);
		$this->aStatusInfo['status'] = 'retrieving';
		$this->aStatusInfo['tmp_file'] = $this->MakeTmpFile('data');
		$this->aStatusInfo['position'] = 0;
		$this->aStatusInfo['total'] = $oSet->Count();

		foreach($this->aStatusInfo['fields'] as $iCol => $aFieldSpec)
		{
			$sExtendedAttCode = $aFieldSpec['sFieldSpec'];
			$sAttCode = $aFieldSpec['sAttCode'];
			$sColLabel = $aFieldSpec['sColLabel'];
				
			switch($sAttCode)
			{
				case 'id':
					$sType = '0';
					break;

				default:
					$oAttDef = MetaModel::GetAttributeDef($aFieldSpec['sClass'], $aFieldSpec['sAttCode']);
					$sType = 'string';
					if($oAttDef instanceof AttributeDate)
					{
						$sType = 'date';
					}
					else if($oAttDef instanceof AttributeDateTime)
					{
						$sType = 'datetime';
					}
			}
			$aTableHeaders[] = array('label' => $sColLabel, 'type' => $sType);
		}

		$sRow = json_encode($aTableHeaders);
		$hFile = @fopen($this->aStatusInfo['tmp_file'], 'ab');
		if ($hFile === false)
		{
			throw new Exception('ExcelBulkExport: Failed to open temporary data file: "'.$this->aStatusInfo['tmp_file'].'" for writing.');
		}
		fwrite($hFile, $sRow."\n");
		fclose($hFile);
		return '';
	}

	public function GetNextChunk(&$aStatus)
	{
		$sRetCode = 'run';
		$iPercentage = 0;

		$hFile = fopen($this->aStatusInfo['tmp_file'], 'ab');

		$oSet = new DBObjectSet($this->oSearch);
		$oSet->SetLimit($this->iChunkSize, $this->aStatusInfo['position']);
		$this->OptimizeColumnLoad($oSet);

		$iCount = 0;
		$iPreviousTimeLimit = ini_get('max_execution_time');
		$iLoopTimeLimit = MetaModel::GetConfig()->Get('max_execution_time_per_loop');
		while($aRow = $oSet->FetchAssoc())
		{
			set_time_limit($iLoopTimeLimit);
			$aData = array();
			foreach($this->aStatusInfo['fields'] as $iCol => $aFieldSpec)
			{
				$sAlias = $aFieldSpec['sAlias'];
				$sAttCode = $aFieldSpec['sAttCode'];

				$oObj = $aRow[$sAlias];
				$sField = '';
				if ($oObj)
				{
					$sField = $this->GetValue($oObj, $sAttCode);
				}
				$aData[] = $sField;
			}
			fwrite($hFile, json_encode($aData)."\n");
			$iCount++;
		}
		set_time_limit($iPreviousTimeLimit);
		$this->aStatusInfo['position'] += $this->iChunkSize;
		if ($this->aStatusInfo['total'] == 0)
		{
			$iPercentage = 100;
			$sRetCode = 'done';  // Next phase (GetFooter) will be to build the xlsx file
		}
		else
		{
			$iPercentage = floor(min(100.0, 100.0*$this->aStatusInfo['position']/$this->aStatusInfo['total']));
		}
		if ($iCount < $this->iChunkSize)
		{
			$sRetCode = 'done';
		}
		$aStatus = array('code' => $sRetCode, 'message' =>  Dict::S('Core:BulkExport:RetrievingData'), 'percentage' => $iPercentage);
		return ''; // The actual XLSX file is built in GetFooter();
	}

	public function GetFooter()
	{
		$hFile = @fopen($this->aStatusInfo['tmp_file'], 'rb');
		if ($hFile === false)
		{
			throw new Exception('ExcelBulkExport: Failed to open temporary data file: "'.$this->aStatusInfo['tmp_file'].'" for reading.');
		}
		$sHeaders = fgets($hFile);
		$aHeaders = json_decode($sHeaders, true);

		$aData = array();
		while($sLine = fgets($hFile))
		{
			$aRow = json_decode($sLine);
			$aData[] = $aRow;
		}
		fclose($hFile);
			
		$fStartExcel = microtime(true);
		$writer = new XLSXWriter();
		$oDateTimeFormat = new DateTimeFormat($this->aStatusInfo['date_format']);
		$writer->setDateTimeFormat($oDateTimeFormat->ToExcel());
		$oDateFormat = new DateTimeFormat($oDateTimeFormat->ToDateFormat());
		$writer->setDateFormat($oDateFormat->ToExcel());
		$writer->setAuthor(UserRights::GetUserFriendlyName());
		$aHeaderTypes = array();
		$aHeaderNames = array();
		foreach($aHeaders as $Header)
		{
			$aHeaderNames[] = $Header['label'];
			$aHeaderTypes[] = $Header['type'];
		}
		$writer->writeSheet($aData,'Sheet1', $aHeaderTypes, $aHeaderNames);
		$fExcelTime = microtime(true) - $fStartExcel;
		//$this->aStatistics['excel_build_duration'] = $fExcelTime;

		$fTime = microtime(true);
		$data = $writer->writeToString();
		$fExcelSaveTime = microtime(true) - $fTime;
		//$this->aStatistics['excel_write_duration'] = $fExcelSaveTime;

		@unlink($this->aStatusInfo['tmp_file']);

		return $data;
	}

	public function GetMimeType()
	{
		return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
	}

	public function GetFileExtension()
	{
		return 'xlsx';
	}

	public function GetSupportedFormats()
	{
		return array('xlsx' => Dict::S('Core:BulkExport:XLSXFormat'));
	}
}