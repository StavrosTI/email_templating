<?php
/*	Excel2Email
 *
 *	Templating engine that pulls columns of data out of an excel spreadsheet and populates those values into placeholders in an html template.  Makes use of the PHPExcel 1.7.8 library to read in xls/xlsx files.
 *
 *	
 */

 error_reporting(E_ALL);
 ini_set('display_errors', TRUE);
 ini_set('dispaly_startup_errors', TRUE);
 date_default_timezone_set('America/New_York');
 
 /** Include PHPExcel */
 require_once 'Classes/PHPExcel.php';
 include 'Classes/PHPExcel/IOFactory.php';
 
 define('EOL',(PHP_SAPI == 'cli') ? PHP_EOL : '<br />');
 define('OPEN_TAG', "##");
 define('CLOSE_TAG', "##");
 
 //For stripping out invalid filename characters
 $invalid_filename_characters = array_merge( 
	array_map('chr', range(0,31)),
	array("<", ">", ":", '"', "/", "\\", "|", "?", "*")); 
 
 $htmlTemplateFile = 'CM_Template.html';
 $filePath = "";
 $destPath = "output/";
 $excelSource = 'OutboundDirect.xlsx';
 $logFile = "logs/Log".date('m-d-Y_H-i-s').".txt";
 //$logFile = 'file.txt';
 if (!file_exists($excelSource)) {
	exit("Missing Excel source file " . $excelSource . ".  Add this file and run this script again.");
 }
 if ( !file_exists($htmlTemplateFile)) {
	exit("Missing HTML template file " . $htmlTemplateFile . ".  Add this file and run this script again.");
 }
 $fileType = PHPExcel_IOFactory::identify($excelSource);
 
 echo "Loading Excel source file '$excelSource'... " . date('H:i:s') . "<br/>";
 file_put_contents($logFile, 'Loading Excel source file... \'' . $excelSource . '\'\t' . date('H:i:s') . '\n', FILE_APPEND | LOCK_EX);
	//change this to accept the excel file from a file uploader.
 $objReader = PHPExcel_IOFactory::createReader($fileType);
 
 //Create new PHPExcel object
 //echo date('H:i:s') , " Create new PHPexcel object...", EOL;
 $objPHPExcel = $objReader->load($excelSource);
 $objWorksheet = $objPHPExcel->getActiveSheet();

 //loop through columns and get all the data
 $agencyData = array();
 $indexColumn = 'A';	//column that has all the placeholder values, usually 'A'
	//get bounds of the data
 $lastRow = $objWorksheet->getHighestRow();
 $lastColumn = $objWorksheet->getHighestColumn();
 $lastColumn++;
 $agencyIterator=0;
 for($curColumn = 'C'; $curColumn != $lastColumn; $curColumn++){ 

	for($row = 2; $row <= $lastRow; $row++) {
	
		$key = $objWorksheet->getCell($indexColumn.$row)->getValue();	//placeholder key
			//var_dump($key);
		$val = $objWorksheet->getCell($curColumn.$row)->getFormattedValue();	//placeholder value
			//var_dump($val);
			//push each key=>val pair into agency index
		//$agencyData[$agencyIterator][] = array($key => $val);
		$agencyData[$agencyIterator][$key] = $val;
	}
	$agencyIterator++;	//iterate the agency index
 }
 echo "Excel source loaded...  " . date('H:i:s') . "<br/><br/>";
 file_put_contents($logFile, "Excel source loaded... " . date('H:i:s') . '\r\n', FILE_APPEND | LOCK_EX);
 
 //var_dump($agencyData);
		
 $htmlSource = array();
 $fh = fopen($htmlTemplateFile, 'r');
 if($fh) {
	
	//iterate through campaigns
	foreach ($agencyData as $agency => $index) {
	
		//var_dump ($index);
			
		//read in template file line by line.
		
		while(!feof($fh)) {
			
			$line = fgets($fh);
			$repstr = "";
			
			//run each placeholder tag against current line of the file.
			foreach ($index as $key => $val) {
				$count = $count_blanks = 0;
				
				// Date formatting
				if (	$key == 'Removal Date' || 
						strstr($key, 'Booking Window') ||
						strstr($key, 'Travel Window') ) {
					
					$val = str_replace('-', '/', $val);	
				}
				
				$tag = OPEN_TAG . trim($key) . CLOSE_TAG;
				//echo $tag . " = " . $val . "<br />";
				$repstr = str_replace($tag, $val, $line, $count);
				
				//check for optional, black travel/booking windows and strip spacing/formatting articles (e.g. 'to', '-', etc.)			
				if ( 	( strstr($key, 'Window') && 
						strstr($key, 'Start') ) && 
						$val == '' ) {
				
					//using $repstr, which already has the tag info replaced
					$repstr = str_replace('to', '', $repstr);
				}
				
				//strip tag for blank booking/travel windows
				if ( 	( strstr($key, 'Booking Window') ||
						strstr($key, 'Travel Window') ) &&
						$val == '' ) {
						
					//echo $tag . "<br>";	
					//using $repstr, which already has the tag info replaced
					$repstr = str_replace($tag, '', $repstr, $count_blanks);
					$repstr = str_replace('<br/>', '', $repstr);
				}

				//var_dump($repstr);
				if ( $count > 0 || $count_blanks > 0 ) { 
					//log replacement event
					//echo "Placeholder tag " . $tag . " replaced. \t" . date('H:i:s') . "<br/>";
					file_put_contents($logFile, "Placeholder tag " . $tag . " replaced. -- " . date('H:i:s') . '\r\n', FILE_APPEND | LOCK_EX);
					break;
				}
				
				
			}
			//push processed line onto array of html content.
			$htmlSource[] .= $repstr;
			
		}
		//var_dump($htmlSource);
		
		//generate the processed html template file.
		$supplier = trim(str_replace($invalid_filename_characters, "_", $index['Supplier']));
		$htmlFileName = "CM_template_" . $supplier . ".html";
		file_put_contents($destPath.$htmlFileName, $htmlSource); 	//write processed html to file
		
		//Console output and logging
		echo "<b>HTML source '" . $destPath . $htmlFileName . "' generated.\t" . date('H:i:s') . "</b><br/><br/>";
		file_put_contents($logFile, "HTML source '" . $destPath . $htmlFileName . "' generated.\\t" . date('H:i:s') . '\n', FILE_APPEND | LOCK_EX);
		
		//reset the file pointer and htmlSource array
		fseek($fh, 0);
		$htmlSource = "";
	}
	fclose($fh);	
 }
?>