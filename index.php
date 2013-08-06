<?php
/** 
 * EmailGenerator
 * 
 * Reads in a Microsoft Excel source file and generates an HTML email based on pre-defined HTML 'modules'.  
 * Files are then FTP'd to predefined paths on QA and Production servers.
 * 
 * Copyright (c) 2013 Travel Impressions
 * 
 * @category   EmailGenerator
 * @package    EmailGenerator
 * @author	   Stavros Louris for Travel Impressions - stavros.louris@travimp.com
 * @copyright  Copyright (c) 2013 Travel Impressions (http://www.travimp.com)
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt	LGPL
 * @version    0.1, 2013-08-06
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
	public $modules = "";							// Modules Directory
	public $assets = "";							// Assets Directory
	public $output = "";							// Output Directory
	private $modulesArray = Array();				// array of files from input folder
	private $assetsArray = Array();					// array of files from input folder
	private $outputArray = Array();					// array of log data
	private $excelSourceSize = "email.html";		// Default name of the email to be generated
	
	private $ftpLogin = "jeffc";					// FTP Login
	private $ftpPass = "cvq5am";					// FTP Password
	private $ftpQaHost = "boa";						// FTP QA host
	private $ftpProdHost = "cobra";					// FTP Production host
	private $ftpPort = "22";						// FTP Port
	
	public $emailName = "email.html";				// Name of the email to be generated
	public $emailBrand = "";						// 'Travel Impressions' or 'American Express'							
	public $emailType = "";							// Name of the email campaign. E.G. 'Marketing Mondays'
	public $emailSubject = "";						// Subject line of the email
	public $emailDeadline = "";						// Due date for the content of the email
	public $emailMailDate = "";						// Mailing dealinge for the email
	
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
	 * Scans the modules directory for html module files and loads them into the 'modulesArray' class prooperty
	 */
	public function load_modules () {
		$this->modulesArray = scandir( $this->modules, 1 );
		unset($this->modulesArray[(count($this->modulesArray)-1)]);
		unset($this->modulesArray[(count($this->modulesArray)-1)]);
		
		$this->totalModules = count($this->modulesArray);
			//var_dump($this->modulesArray);
	}
	
	/**
	 * Returns the requested module HTML
	 */
	public function get_module ( $moduleName ) {
	
		$remove = array ("module", "");
		$moduleName = str_replace( $remove, "", strtolower($moduleName) );
		$moduleName .= '.html';
			//var_dump($moduleName);
			
		if ( in_array( $moduleName, $this->modulesArray ) ) {
			$moduleHtml = file_get_contents( $this->modules.$moduleName );
				//var_dump($moduleHtml);
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
			//var_dump($this->assetsArray);
	}
	
	/**
	 * Initializes the email generation parameters
	 */
	public function initialize_email_parameters ( $type, $brand ) {
		
		//ToDo:  Put parameters in a config files and read in.
			//var_dump($brand);
			//var_dump($type);
			
		$params = "";
		
		if ( $brand == "Travel Impressions" ) { 

			$params['paths'] = array(
				'qa_html' => "http://qa.travimp.com/email/",
				'qa_img' => "http://qa.travimp.com/email/img/",
				'prod_html' => "http://www.travimp.com/email/",
				'prod_img' => "http://www.travimp.com/email/img/", 
			);
		} elseif ( $brand == "American Express" ) {

			$params['paths'] = array(
				'qa_html' => "http://qa.myaev.com/email/",
				'qa_img' => "http://qa.myaev.com/email/img/",
				'prod_html' => "http://www.myaev.com/email/",
				'prod_img' => "http://www.myaev.com/email/img/" 
			);
		}
		
		return $params;
	}
	
	/**
	 * Read in Excel Source File.  Reads in the 'header' information seperately, then reads in one module section at a time.
	 * 
	 * @param string	sourceFileName	File name for PHPExcel class to work with. 
	 */
	public function read_excel_source ( $sourceFileName ) {
		
		$fileType = PHPExcel_IOFactory::identify($sourceFileName);
		$objReader = PHPExcel_IOFactory::createReader($fileType);
		
		//Create new PHPExcel object. log
		$objPHPExcel = $objReader->load($sourceFileName);
		$objWorksheet = $objPHPExcel->getActiveSheet();
		
		//Reader Excel Source 'Header'
		$this->emailName = str_replace( " ", "", ucwords( strtolower( $objWorksheet->getCell('B1')->getValue() ) ) );
		//$this->emailType = str_replace( " ", "", ucwords( strtolower( $objWorksheet->getCell('B2')->getValue() ) ) );
		$this->emailType = ucwords( strtolower( $objWorksheet->getCell('B2')->getValue() ) );
		//$this->emailBrand = str_replace( " ", "", ucwords( strtolower( $objWorksheet->getCell('B3')->getValue() ) ) );
		$this->emailBrand = ucwords( strtolower( $objWorksheet->getCell('B3')->getValue() ) );
		$this->emailSubject = ucwords( strtolower( $objWorksheet->getCell('B4')->getValue() ) );
		$this->emailDeadline = str_replace( " ", "", ucwords( strtolower( $objWorksheet->getCell('B5')->getValue() ) ) );
		$this->emailMailDate = str_replace( " ", "", ucwords( strtolower( $objWorksheet->getCell('B6')->getValue() ) ) );
		
		$lastRow = $objWorksheet->getHighestRow();
		
		$emailData = array();
		$module = '';
		for($row = 7; $row <= $lastRow; $row++) {
		
			$tag = $objWorksheet->getCell('A'.$row)->getValue();	//placeholder key
				//var_dump($tag);
			if ( stripos($tag, 'module') === FALSE ) {
				$spec = $objWorksheet->getCell('B'.$row)->getFormattedValue();
				$type = $objWorksheet->getCell('C'.$row)->getFormattedValue();
				$val = $objWorksheet->getCell('D'.$row)->getFormattedValue();
				//var_dump($val);
					
				$emailData[$module][$tag]['type'] = $type;
				$emailData[$module][$tag]['spec'] = $spec;
				$emailData[$module][$tag]['val'] = $val;
			} else {
			
				$module = $tag;
					//echo "Start of new module: " . $module . "<br>";
			}
		}
		
		return $emailData;
	}
	
	/**
	 * Parses all the 'modules' in the emailData array and replaces out the tags from each module html set.  Builds the email in the order the array is stacked.
	 *
	 * @param array	$emailData	array with all the email data pulled from the excel template
	 * @param bool	$qa			runs the method in 'qa' mode, linking all images to the the qa server
	 */
	public function build_html ( $emailData, $qa=0 ) {
	
		//TODO: add image dimensions and type checker.
	
		//Adding opening wrapper
		$topHtml = $this->get_module('top');
		$topHtml = str_replace('##EmailType##', $this->emailType, $topHtml);
		$topHtml = str_replace('##EmailBrand##', $this->emailBrand, $topHtml);
		$emailHtml = $topHtml;
		
		$params = $this->initialize_email_parameters( $this->emailType, $this->emailBrand );
			//var_dump($params);
			
		//Iterate through the modules
		foreach( $emailData as $key=>$val ) {
		
				//echo "Processing: ", $key, "<br>";
				//var_dump($val);
			$curModuleHtml = $this->get_module( $key );
				//var_dump($curModuleHtml);
			
			if ( $curModuleHtml ) {
			
				$replacedModuleHtml = $curModuleHtml;

				//iterate throught the module tags
				foreach ( $val as $tag=>$tagDetails ) {
				
					if ( $tagDetails['type'] == 'url' ) {
						//validate URL
						$file_headers = @get_headers($tagDetails['val']);
						if($file_headers[0] == 'HTTP/1.1 404 Not Found') {
							echo "<div class=\"msg-warn\">Warning: " . $tagDetails['val'] . "<br> is not a valid URL.</div>";
						}
					}
					if ( $tagDetails['type'] == 'img' ) {
						$path = ( $qa ) ? $params['paths']['qa_img'] : $params['paths']['prod_img'];
						$tagDetails['val'] = $path.$tagDetails['val'];
						
						// TODO: validate image dimensions
					}
					if ($tagDetails['type'] == 'text' ) {
						if ( strlen($tagDetails['val']) > $tagDetails['spec'] ) {
							echo "<div class=\"msg-warn\">Warning: Character count for '" . $tag . "' is more than " . $tagDetails['spec'] . " characters.</div>";
						}
					}
					if ( $tagDetails['type'] == 'html' ) {
						
						// TODO: HTML validation
						
					}
					
					$tag = str_replace ( " ", "", OPEN_TAG . trim($tag) . CLOSE_TAG );
						//var_dump($tag);
						//var_dump($tagDetails);
						
					$replacedModuleHtml = str_replace($tag, $tagDetails['val'], $replacedModuleHtml, $count);
				}
				
				//var_dump($replacedModuleHtml );
				$emailHtml .= $replacedModuleHtml;
			}	
		}
		
		//Adding closing wrapper
		$emailHtml .= $this->get_module('bottom');
		
		return $emailHtml;
	}
	
	/**
	 * FTP functionality
	 *
	 * @param string 	fileName	Name of HTML source to ftp up
	 * @param bool		assets		Flag, upload image assets
	 * @param bool		qa			Flag, upload to qa
	 */
	public function push_ftp( $fileName, $assets=1, $qa=0 ) {
		// FTP script adapted from http://nirvaat.com/blog/web-development/uploading-files-ftp-server-php-script/
	
		//TODO: Put remote assets in a config file and initialize on run.
	
		//Setup the remote directories
		if ( $qa ) {
			if ( $this->emailBrand == "Travel Impressions" ) {
				$remote_html_dir = "/home/sites/fyi/email";
				$remote_assets_dir = "/home/sites/fyi/email/img";
			} elseif ( $this->emailBrand == "American Express" ) {
				$remote_html_dir = "/home/sites/myaev/email";
				$remote_assets_dir = "/home/sites/myaev/email/img";
			}
		} else {
			if ( $this->emailBrand == "Travel Impressions" ) {
				$remote_html_dir = "/home/web/email";
				$remote_assets_dir = "/home/web/email/img";
			} elseif ( $this->emailBrand == "American Express" ) {
				$remote_html_dir = "/home/myaev/email";
				$remote_assets_dir = "/home/myaev/email/img";
			}
		}
		//Don't FTP anything if no brand specified		
		if ( $remote_html_dir == "" && $remote_assets_dir == "" ) { 
			echo "<div class=\"msg-error\">No email brand specified in source.  No files have been uploaded.</div>";
			return FALSE; 
		}

		// set up basic connection
		$ftp_server = ( $qa ) ? $this->ftpQaHost : $this->ftpProdHost;
		$conn_id = ( $qa ) ? ftp_connect($ftp_server) :  ftp_connect($ftp_server);

		// login with username and password
		$login_result = @ftp_login($conn_id, $this->ftpLogin, $this->ftpPass);
		
		//default values
		$file_url = $fileName;

		if($login_result) {
			//set passive mode enabled
			ftp_pasv($conn_id, true);

			//// FTP email HTML
			ftp_chdir($conn_id, $remote_html_dir);

			$file = $this->output . $file_url;
			$remote_file = $file_url;
				
			//echo "Copying '" . $fileName . "' to '(" . $ftp_server . ") " . $remote_html_dir . "/" . $remote_file . "<br>";
			
			//Check if file already exists and replace (delete, then write new file)
			if ( ftp_size($conn_id, $remote_file) > -1 ) { 
				echo "<div class=\"msg-info\">File '" . $remote_file . "' already exists on server....</div>";
				if ( ftp_delete( $conn_id, $remote_file ) ) {
					echo "<div class=\"msg-success\">The file '". $remote_file . "' was successfully removed and will be replaced.</div>";
				} else {
					echo "<div class=\"msg-error\">There was an error replacing the file '". $remote_file . "'.</div>";
				}
			}
				
			$ret = ftp_nb_put($conn_id, $remote_file, $file, FTP_BINARY, FTP_AUTORESUME);
			while(FTP_MOREDATA == $ret) {
				$ret = ftp_nb_continue($conn_id);
			}

			if($ret == FTP_FINISHED) {
				echo "<div class=\"msg-success\">File '" . $remote_file . "' uploaded successfully.</div>";
			} else {
				echo "<div class=\"msg-error\">Failed uploading file '" . $remote_file . "'.</div>";
			}
			
			//// FTP email image assets
				//var_dump($this->assetsArray);
			
			ftp_chdir($conn_id, $remote_assets_dir);
			
			foreach ( $this->assetsArray as $img_asset ) {
				
				$file = $this->assets . $img_asset;
				$remote_file = $img_asset;
					
				//echo "<div class=\"msg-info\">Copying '" . $img_asset . "' to '(" . $ftp_server . ") " . $remote_assets_dir . "/" . $remote_file . "</div>";
				
				//Check if file already exists and replace (delete, then write new file)
				if ( ftp_size($conn_id, $remote_file) > -1 ) { 
					echo "<div class=\"msg-info\">File '" . $remote_file . "' already exists on server....</div>";
					if ( ftp_delete( $conn_id, $remote_file ) ) {
						echo "<div class=\"msg-success\">The file '". $remote_file . "' was successfully removed and will be replaced.</div>";
					} else {
						echo "<div class=\"msg-error\">There was an error replacing the file '". $remote_file . "'.</div>";
					}
				}				
				
				$ret = ftp_nb_put($conn_id, $remote_file, $file, FTP_BINARY, FTP_AUTORESUME);
				while(FTP_MOREDATA == $ret) {
					$ret = ftp_nb_continue($conn_id);
				}

				if($ret == FTP_FINISHED) {
					echo "<div class=\"msg-success\">File '" . $remote_file . "' uploaded successfully.</div>";
				} else {
					echo "<div class=\"msg-error\">Failed uploading file '" . $remote_file . "'.</div>";
				}
				
			}
			
		} else {
			echo "<div class=\"msg-error\">Cannot connect to FTP server at " . $ftp_server . "</div>";
		}
	}
	
	/**
	 * Write the html to the 'output' folder
	 *
	 * @param string	emailHtml	Contents of the Html email
	 * @param string	emailName	Name of the output file.  If blank, takes the name from class property
	 * @return string				The name used for output
	 */
	public function dump_html( $emailHtml, $emailName="" ) {
	
		//ToDo: Add QA version to output
	
		$emailName = ( $emailName != "" ) ? $emailName : $this->emailName.'.html';
		$emailPath = $this->output.$emailName;
		
		$result = file_put_contents($emailPath, $emailHtml);
		
		return ( $result === FALSE ) ? FALSE : $emailName;
	}
	
	/**
	 * Output the QA and Live Html Links
	 * 
	 * @param string 	emailName	Name of the email
	 * @param bool		qa			Output QA Link
	 * @param bool		prod		Output Production Link
	 */
	public function output_links( $emailName, $qa=1, $prod=1 ) {
	
		$params = $this->initialize_email_parameters( $this->emailType, $this->emailBrand );
			//var_dump($params);
			
		if ( $qa ) {
			echo "<div class=\"output-links\">QA Email Link: ";
			echo "<a href=\"". $params['paths']['qa_html'] . $emailName . "\" target=\"_blank\">";
			echo $params['paths']['qa_html'] . $emailName;
			echo "</a><br><br>";
		}
		if ( $prod ) {
			echo "<div class=\"output-links\">Live Email Link: ";
			echo "<a href=\"". $params['paths']['prod_html'] . $emailName . "\" target=\"_blank\">";
			echo $params['paths']['prod_html'] . $emailName;
			echo "</a><br>";
		}
		if ( !$qa && !$prod ) {
			echo "<div class=\"output-links\">HTML is in the output folder under '<i>" . $emailName . "</i>'.</div>";
		}
		return 0;
	}
}	//close EmailGenerator class
?>

<!DOCTYPE html>
<html>
<head>
<title>Marketing Email Generator - Travel Impressions</title>
<link rel="stylesheet" type="text/css" href="style.css">
</head>
<body> 
 
<?php
 
if ( isset($_POST['submit']) ) { 
	//$_FILES['excelsource']['error'] != 4
	//var_dump($_POST);	//var_dump($_FILES);
	
	//Create new 'EmailGenerator' object.
	$email = new EmailGenerator(
		__DIR__ . '/modules/',
		__DIR__ . '/assets/',
		__DIR__ . '/output/',
		$_FILES['excelSource']['size']
	);
	
	// Todo: validate mime types of template/source.
	//$replacer->file_validator( $_FILES['excelSource'],0,1);
	
	
	$emailData = $email->read_excel_source( $_FILES['excelSource']['tmp_name'] );
		//var_dump($emailData);
	$email->get_assets();
	$email->load_modules();
				
	echo "<p>Excel Source: " . $_FILES['excelSource']['name'] . "<br>";
	echo "Email Name: " . $email->emailName . "<br>";
	echo "Email Brand: " . $email->emailBrand . "<br>";
	echo "Email Type: " . $email->emailType . "</p>";
 
	//Build, Output, and FTP QA version of email
	echo "<p>Processing QA Version...</p>";
	$emailHtml = $email->build_html( $emailData, 1);
	$dump_result = $email->dump_html( $emailHtml );
	if ( isset($_POST['qa_version']) ) {
		$email->push_ftp( $dump_result, 1, 1 );
	}

	//Build, Output, and FTP PROD version of email
	echo "<p>Processing Production Version...</p>";
	$emailHtml = $email->build_html( $emailData);
	$dump_result = $email->dump_html( $emailHtml );
	if ( isset($_POST['prod_version']) ) {
		$email->push_ftp( $dump_result, 1 );
	}
	
	echo "<hr>";

	//Print links to QA or Production emails
	if ( isset($_POST['qa_version']) ) {
		$email->output_links( $dump_result, 1, 0 );
	}
	if ( isset($_POST['prod_version']) ) {
		$email->output_links( $dump_result, 0, 1 );
	}
	$email->output_links( $dump_result, 0, 0 );
	
	echo "<br>";
	echo "<a href=\"" . $_SERVER['PHP_SELF'] . "\">Back to Generator</a>";
 }
  
if (!isset($_POST['submit']) ) {
?>

<h1>Marketing Email Generator</h1>
<h2>For use by: Email Marketing Interactive Artist</h2>
<p>Takes excel spreadsheet template as input.  Reads in that data and any files from the 'assets' folder to generate the email.</p>
 
<form enctype='multipart/form-data' action='<?php echo $_SERVER['PHP_SELF']; ?>' method='POST'>
	<fieldset>
		<legend>Required Assets:</legend>
		<div class="form_item">
			<label for="excelSource">Excel Source File</label>:<br />
			<input type="hidden" name="MAX_FILE_SIZE" value="1024000" />
			<input type="file" name="excelSource" id="termsSource" size="50" />
		</div>
	</fieldset>
	<br>
	<input type="checkbox" name="qa_version" value="qa_version" CHECKED>Create QA Version<br>
	<input type="checkbox" name="prod_version" value="prod_version">Create Production Version<br>
	<br>
	<input type="submit" name="submit" value="Generate Email">
</form>

<?php } ?>
 
</body>
</html> 