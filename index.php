<?php
/** 
 * EmailGenerator
 *
 *
 * 
 * Copyright (c) 2013 Travel Impressions
 * 
 * @category   EmailGenerator
 * @package    EmailGenerator
 * @author	   Stavros Louris for Travel Impressions - stavros.louris@travimp.com
 * @copyright  Copyright (c) 2013 Travel Impressions (http://www.travimp.com)
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt	LGPL
 * @version    0.1, 2013-07-29
 */

 //Global Error Reporting
 error_reporting(E_ALL);
 ini_set('display_errors', TRUE);
 ini_set('dispaly_startup_errors', TRUE);
 date_default_timezone_set('America/New_York');
 
 /** Include PHPExcel */
 require_once 'classes/Classes/PHPExcel.php';
 include 'classes/Classes/PHPExcel/IOFactory.php';
 
 //Invalid filename characters
 $invalidFilenameCharacters = array_merge( 
		array_map('chr', range(0,31)),
		array("<", ">", ":", '"', "/", "\\", "|", "?", "*")
	); 

 class EmailGenerator {
 
	/** Class Properties **/
	private $modules = "";							// Modules Directory
	private $assets = "";							// Assets Directory
	private $output = "";							// Output Directory
	private $modulesArray = Array();				// array of files from input folder
	private $assetsArray = Array();					// array of files from input folder
	private $excelSourceSize = "email.html";		// Name of the email to be generated
	private $emailName = "email.html";				// Name of the email to be generated
	
	private $ftpLogin = "";							// FTP Login
	private $ftpPass = "";							// FTP Password
	private $ftpPort = "";							// FTP Port
	
	private	$logPath = "logs/";						// path for logs
	private $logFile = "";							// log file
	private $err = "";								// err variable 

	private $startTime = '';						// processing start time, unix timestamp
	private $lastTime = '';							// last milestone, unix timestamp
	
 
	/**
	 * Constructor method
	 */
	function __construct( $modules="", $assets="", $output="", $sourceSize="", $logFile="", $logPath="" ) {
		$this->terms = "";
		
		$this->modules = ( $modules != "" )	? $modules : 'modules/';			//modules directory
		$this->assets = ( $assets != "" )	? $assets : 'assets/';				//input directory
		$this->output = ( $output != "" ) ? $output : 'output/';				//output directory
		$this->excelSourceSize = ($sourceSize != "") ? $sourceSize : $this->excelSourceSize;
		
		$this->logPath = ( $logPath != "" ) ? $logPath : $this->logPath;
		$this->logFile = ( $logFile != "" ) ? $logFile : "Log_".date('m-d-Y_H-i-s').".txt";
		$this->logFile = ( $logPath == "" ) ? $this->logPath.$this->logFile : $logPath.$this->logFile ;
		
		define('OPEN_TAG', "##");
		define('CLOSE_TAG', "##");
	}
	
	/**
	 * Logging method
	 *
	 * @param string	$logData	Log data
	 */
	private function write_log ( $logData ) {
		file_put_contents($this->logFile, $logData . " - " . date('m-d-Y H:i:s'), FILE_APPEND | LOCK_EX);
		file_put_contents($this->logFile, "Total processing time: " . $this->timer(2), FILE_APPEND | LOCK_EX);
	}
	
	/**
	 * Record replcement results to outputArray
	 * 
	 * @param string	$fileName		Name of processed file
	 * @param string	$result			Result message of replacement
	 * @param int		$displayOutput	Display bit.  Show results on screen or not.	
	 */
	private function record_output ( $fileName, $result, $term, $mark, $displayOutput=0 ) {
	
		$this->outputArray[] = Array( $fileName, $result, $mark );
		
		//If displayOutput parameter is set
		if ( $displayOutput ) {
			echo "<div class=\"output\">File: " . $fileName . ". Result: " . $result . ". Process Time: " . $mark . "</div>";
		}
	}
	
	/**
	 * Write output array to log
	 *
	 * @return bool 
	 */
	public function dump_output_results () {
		
		$output = '';
		foreach ( $this->outputArray as $entry ) {
			$output .= "File: " . $entry[0] . ". Result: " . $entry[1] . ". Process Time: " . $entry[2] . "\n\r";
		}
		return file_put_contents( $this->logFile, $output );
	}
	
	/**
	 * Write processed file data to output directory.  Increment # replacements, if any.
	 *
	 * @param string		$fileName	Name of file being written
	 * @param string		$fileData	Output file contents
	 * @param int | bool	$changed	Changed flag. If there were replacements, flag will be set.
	 *
	 * @return bool	File write results
	 */
	private function write_replaced_files ( $fileName, $fileData, $changed ) {
	
		if ( $changed ) {
			return file_put_contents( $this->output.$fileName, $fileData );
		} else {
			return file_put_contents( $this->unchanged.$fileName, $fileData );
		}
	}
	
	/**
	 * Internal process timer.  Records timer milestone or resets timer, unix timestamps.
	 *
	 * @param int	$reset		Resets the starting point
	 * @param int	$verbose	returns interval times. 1 for mark time.  2 for total time.
	 *
	 * @return 
	 */
	public function timer ( $verbose=1, $reset=0 ) {
	
		if ( $reset ) {
			$this->startTime = $this->lastTime = microtime();	//reset timer
			//echo "starting time: " . $this->startTime . "<br>";
		} else {
			$mark = microtime() - $this->lastTime;
			$this->lastTime = microtime();
			if ( $verbose == 1 ) {
				return $mark;
			} elseif ( $verbose == 2 ) {
				
				//echo "Ending time: " . microtime() . "<br>";
				return ( microtime() - $this->startTime );
			}
		}
	}
	
	/**
	 * Internal stats counter.
	 *
	 * @param int	$reset		Resets the starting point
	 * @param int	$verbose	returns interval times. 1 for mark time.  2 for total time.
	 *
	 * @return 
	 */
	public function stats ( $editMode=0, $verbose=0, $filesChanged=0, $changes=0 ) {
	
		if ( $editMode ) {
			$this->totalReplacedFiles += $filesChanged;
			$this->totalReplacements += $changes;
		}
		if ( $verbose == 1 ) {
			return $this->totalInputFiles;
		}
		if ( $verbose == 2 ) {
			return $this->totalReplacedFiles;
		}
		if ( $verbose == 3 ) {
			return $this->totalReplacements;
		}
	}
	
	/**
	 * Mimetype checker
	 *
	 * @param string		$fileData	File to check mimetype on
	 * @param int | bool	$type		Category of file to check.  Application dependent
	 * @param int | bool	$verbose	Verbose output bit
	 */
	public function file_validator ( $fileData, $type, $verbose=0 ) {

		// type 0 = template,	type 1 = source

		$mime_types = array(
			'txt' => 'text/plain',
			'htm' => 'text/html',
			'html' => 'text/html',
			'php' => 'text/html',
			'css' => 'text/css',
			'js' => 'application/javascript',
			'json' => 'application/json',
			'xml' => 'application/xml',
			'swf' => 'application/x-shockwave-flash',
			'flv' => 'video/x-flv',

			// images
			'png' => 'image/png',
			'jpe' => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'jpg' => 'image/jpeg',
			'gif' => 'image/gif',
			'bmp' => 'image/bmp',
			'ico' => 'image/vnd.microsoft.icon',
			'tiff' => 'image/tiff',
			'tif' => 'image/tiff',
			'svg' => 'image/svg+xml',
			'svgz' => 'image/svg+xml',

			// archives
			'zip' => 'application/zip',
			'rar' => 'application/x-rar-compressed',
			'exe' => 'application/x-msdownload',
			'msi' => 'application/x-msdownload',
			'cab' => 'application/vnd.ms-cab-compressed',

			// audio/video
			'mp3' => 'audio/mpeg',
			'qt' => 'video/quicktime',
			'mov' => 'video/quicktime',

			// adobe
			'pdf' => 'application/pdf',
			'psd' => 'image/vnd.adobe.photoshop',
			'ai' => 'application/postscript',
			'eps' => 'application/postscript',
			'ps' => 'application/postscript',

			// ms office
			'doc' => 'application/msword',
			'rtf' => 'application/rtf',
			'xls' => 'application/vnd.ms-excel',
			'ppt' => 'application/vnd.ms-powerpoint',
			'docx' => 'application/msword',
			'xlsx' => 'application/vnd.ms-excel',
			'pptx' => 'application/vnd.ms-powerpoint',

			// open office
			'odt' => 'application/vnd.oasis.opendocument.text',
			'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
			);
			
		$valid_mime_types = array ( 
								0 => array('text/plain', 'text/html'),
								1 => array('application/vnd.ms-excel')
								);

		$ext = strtolower( array_pop( explode('.',$fileData['name'] ) ) );
		$mimeType = '';
		
		if(function_exists('mime_content_type')) { 
			$mimeType = mime_content_type($fileData['tmp_name']);
		} elseif(function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME);
			$mimeType = finfo_file($finfo, $fileData['tmp_name']);
			finfo_close($finfo);
		} elseif(array_key_exists($ext, $mime_types)) {
			$mimeType = $mime_types[$ext];
		} else {
			$mimeType = 'application/octet-stream';
		}
		
		//var_dump($mimeType);
		
		if ( in_array( $mimeType, $valid_mime_types[$type]) ) {
			return ($verbose) ? $mimeType : 0;
			
			//set $mimeType as object property.  For use in 'load_excel_content()'.  See PHPExcel_IOFactory::createReader()
			//$this->excelType = $mimeType;
		} else {
			return ($verbose) ? 
				($type) ? "Invalid source file: $mimeType" : "Invalid template file: $mimeType"
					: 
				($type) ? "Invalid source file." : "Invalid template file.";
		}
	}
	
	/**
	 * Reads the 'terms' file and loads into the terms class property
	 * 
	 * @param string $file	File name
	 */
	public function get_replacement_terms ($file) {
		//reading in 'terms' file
		$fh = fopen($file, 'r');
		if ( $fh ) {
			$i=0;
			
			while(!feof($fh)) {
				$line = fgets($fh);
				$this->terms[$i] = explode( $this->termsSeperator, $line );
				$i++;
			}
		}
		var_dump($this->terms);
	}
	
	/**
	 * Scans the modules directory for html module files and loads them into the 'modulesArray' class prooperty
	 */
	public function load_modules () {
		$this->modulesArray = scandir( $this->modules, 1 );
		unset($this->modulesArray[(count($this->modulesArray)-1)]);
		unset($this->modulesArray[(count($this->modulesArray)-1)]);
		
		$this->totalModules = count($this->modulesArray);
		var_dump($this->modulesArray);
	}
	
	/**
	 * Returns the requested module HTML
	 */
	public function get_module ( $moduleName ) {
	
		$remove = array ("module", "");
		$moduleName = str_replace( $remove, "", strtolower($moduleName) );
		$moduleName .= '.html';
		var_dump($moduleName);
		if ( in_array( $moduleName, $this->modulesArray ) ) {
			$moduleHtml = file_get_contents( $this->modules.$moduleName );
			var_dump($moduleHtml);
			return $moduleHtml;
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Scans the assets directory for files and loads them into the 'assetsArray' class prooperty
	 */
	public function get_assets () {
		$this->assetsArray = scandir( $this->assets, 1 );
		unset($this->assetsArray[(count($this->assetsArray)-1)]);
		unset($this->assetsArray[(count($this->assetsArray)-1)]);
		
		$this->totalAssets = count($this->assetsArray);
		var_dump($this->assetsArray);
	}
	
	/**
	 * Read in Excel Source File.  Reads in the 'header' information seperately, then reads in one module section at a time.
	 */
	public function read_excel_source ( $sourceFileName ) {
	
		//TODO: add url link checker.
		//TODO: add specs checker.
		
		$fileType = PHPExcel_IOFactory::identify($sourceFileName);
		$objReader = PHPExcel_IOFactory::createReader($fileType);
		
		//Create new PHPExcel object. log
		$objPHPExcel = $objReader->load($sourceFileName);
		$objWorksheet = $objPHPExcel->getActiveSheet();
		
		//Reader Excel Source 'Header'
		$this->emailName = str_replace( " ", "", ucwords( strtolower( $objWorksheet->getCell('B1')->getValue() ) ) );	//Hard-coded into Excel Template
		$mailingType = str_replace( " ", "", ucwords( strtolower( $objWorksheet->getCell('B4')->getValue() ) ) );	//Hard-coded into Excel Template
		$mailingBrand = str_replace( " ", "", ucfirst( strtolower( $objWorksheet->getCell('B5')->getValue() ) ) );	//Hard-coded into Excel Template
		
		$lastRow = $objWorksheet->getHighestRow();
		
		$emailData = array();
		for($row = 6; $row <= $lastRow; $row++) {
		
			//Can check key here to see if start of 'module'
			$tag = $objWorksheet->getCell('A'.$row)->getValue();	//placeholder key
				//var_dump($tag);
			if ( stripos($tag, 'module') === FALSE ) {
				$spec = $objWorksheet->getCell('B'.$row)->getFormattedValue();	//placeholder value
				$val = $objWorksheet->getCell('C'.$row)->getFormattedValue();	//placeholder value
				//var_dump($val);
					
				$emailData[$module][$tag]['spec'] = $spec;
				$emailData[$module][$tag]['val'] = $val;
				//$emailData[$tag]['spec'] = $spec;
				//$emailData[$tag]['val'] = $val;
			} else {
			
				$module = $tag;
					//echo "Start of new module: " . $module;
			}
		}
		return $emailData;
	}
	
	/**
	 * Loops through all the files in the 'inputArray' property array and replaces out strings in the 'terms' property array
	 */
	public function build_html ( $emailData ) {
	
		$emailHtml = "";
		//var_dump($emailData);	
			
		//Iterate through the modules
		foreach( $emailData as $key=>$val ) {
		
			echo "Processing: ", $key, "<br>";
			//var_dump($val);
			$curModuleHtml = $this->get_module( $key );
			//var_dump($curModuleHtml);
			
			if ( $curModuleHtml ) {
			
				$replacedModuleHtml = $curModuleHtml;

				//iterate throught the module tags
				foreach ( $val as $tag=>$tagDetails ) {
					
					$tag = str_replace ( " ", "", OPEN_TAG . trim($tag) . CLOSE_TAG );
					
						var_dump($tag);
						//var_dump($tagDetails);
					$replacedModuleHtml = str_replace($tag, $tagDetails['val'], $replacedModuleHtml, $count);
				}
				
				//var_dump($replacedModuleHtml );
				$emailHtml .= $replacedModuleHtml;
			}	
		}
		
		return $emailHtml;
		
		/*
			$fileName = $val;	//easier to understand
			$filePath = $this->input.$fileName;
			$fh = fopen($filePath, 'r');
			$fileData = fread($fh, filesize($filePath));

			$total_count = 0;
		
			if ( $fileData ) {
				$fileData_replaced = $fileData;
				
				foreach ($this->terms as $terms) {
					$result = '';
					
					
					//echo "Replacing this: ", $terms[0], "<br>";
					//echo "with this: ", $terms[1], "<br>";
					//echo "File: " . $filePath . "<br>";
					

					$fileData_replaced = str_replace($terms[0], $terms[1], $fileData_replaced, $count);
					//echo "Replaced text: <br>" . $fileData_replaced . "<br>";
					$result = ( $count ) ? "Success" : "No Replacement/Failure";

					//record replacement results
					$this->record_output( $fileName, $result, $terms[0], $this->timer() );
					
					//record statistics
					if ( $count ) {	
						$this->stats( 1, 0, 1, $count);	
						$total_count += $count;
					}
				}
				
				echo "File: ", $filePath, "<br>";
				echo "Total File Replacements: ", $total_count, "<br><br>";
				//Write output file to output directory
				$this->write_replaced_files( $fileName, $fileData_replaced, $total_count );	
				
				// After all terms have been run against the file, close the handler.
				fclose($fh);
			}
		}
		*/
	}
	
	/**
	 * Loops through all the files in the 'inputArray' property array and replaces out strings in the 'terms' property array
	 */
	public function dump_html ( $emailHtml, $emailName="" ) {
	
		$emailName = ( $emailName != "" ) ? $emailName : $this->emailName;
		$result = file_put_contents($this->output.$emailName, $emailHtml);
		
		return ( $result === FALSE ) ? FALSE : TRUE;
	}
}	//close EmailGenerator class
 
 if ( isset($_POST['submit']) ) { 
	
	//var_dump($_POST);	//var_dump($_FILES);
	
	//Create new 'EmailGenerator' object.
	$email = new EmailGenerator(
		__DIR__ . '/modules/',
		__DIR__ . '/assets/',
		__DIR__ . '/output/',
		$_FILES['excelSource']['size']
	);
	
	//validate mime types of template/source
	//$replacer->file_validator( $_FILES['excelSource'],0,1);
	
	$emailData = $email->read_excel_source( $_FILES['excelSource']['tmp_name'] );
		//var_dump($emailData);
	$email->get_assets();
	$email->load_modules();
 
	//$email->timer(0,1);		//start the timer
	$emailHtml = $email->build_html( $emailData );
	$email->dump_html( $emailHtml );
	
	//FTP HTML and assets
	
	/*
	echo "<hr>";
	echo "Total processing time: " . $email->timer(2) . "s<br>";
	
	echo "Total input files: " . $email->stats(0,1) . "<br>";
	echo "Total files with replacements: " . $email->stats(0,2) . "<br>";
	echo "Total file replacements: " . $email->stats(0,3) . "<br>";
	*/
	
	//$email->dump_output_results();
 }
  
if (!isset($_POST['submit']) || isset($err) ) {
?>

 <h1>Marketing Email Generator</h1>
 <h3>For use by: Email Marketing Interactive Artist</h3>
 <p>Take excel spreadsheet template as input.  Reads in that data and any files from the 'assets' folder to generate the email.</p>
 
 <form enctype='multipart/form-data' action='<?php echo $_SERVER['PHP_SELF']; ?>' method='POST'>
	<fieldset>
		<legend>Required Assets:</legend>
		<div class="form_item">
			<label for="excelSource">Excel Source File</label>:<br />
			<input type="hidden" name="MAX_FILE_SIZE" value="1024000" />
			<input type="file" name="excelSource" id="termsSource" size="50" />
		</div>
		<br>
		<input type="submit" name="submit" value="Generate Email">
	</fieldset>
 </form>
 
 <?php } ?>