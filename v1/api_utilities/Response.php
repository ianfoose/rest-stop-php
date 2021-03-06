<?php
/**
* Response Class
*
* @copyright Foose Industries
* @version 1.0
*/
class Response {
    /**
    * @var string $format Output format
    */
    public $format = 'text';
    
    /**
    * @var int $httpStatus HTTP Status Code
    */
    public $httpStatus = 200;
    
    /**
    * @var array $errorArray Error Message for HTTP Status code 
    */
    public static $errorArray = array(
        200=>'OK',
        201=>'Created',
        204=>'No Content',
        304=>'Not Modifed',
        400=>'Bad Request',
        401=>'Unauthorized',
        403=>'Forbidden',
        404=>'Not Found',
        405=>'Method Not Allowed',
        409=>'Conflict',
        500=>'Internal Server Error'
    );

    /**
    * Main Constructor
    *
    * @param string $format Response format
    * @return void
    */
    public function __construct($format='json', $errorArray=null) {
        $this->format = $format;

        if(!empty($errorArray)) {
            foreach ($errorArray as $key => $value) {
                if(!empty($value)) {
                    $this->errorArray[$key] = $value;
                }
            }
        }
    }
    
    /**
    * Gets a messages for status code
    *
    * @param int $status Response HTTP status
    * @return string
    */
    private function getMessage($status=200) {
        if(isset(self::$errorArray[$status])) {
          return self::$errorArray[$status];
        } 
    }

    /**
    * sends data to the client
    *
    * @param array $data Data array
    * @param int $status Response HTTP status
    * @param array $headers Output headers
    * @return void
    */
    public function send($data=null, $status=200, $headers=null) {
        // check if data is an exception
        if(!empty($data) && $data instanceof Exception) {
           if($data->getCode() != null) {
              $status = $data->getCode();
            }   
  
            $msg = self::$errorArray[$status];

            if($data->getMessage() != null) {
                $msg .= ', '.$data->getMessage(); 
            }   

            $data = array('result'=>$msg);
        }

        $headerString = ('HTTP/1.1'.' '.$status.' '.$this->getMessage($status));

        header($headerString);
        
        if(!empty($headers)) {
            if(!is_array($headers)) {
                $preFormattedHeader = explode(':', $headers);
                $headers = array($preFormattedHeader[0]=>$preFormattedHeader[1]);
            }

            foreach ($headers as $key => $value) {
                header("$key: $value");
            }
        }

        if((!empty($headers) && is_array($headers) && !array_key_exists('Content-Type', $headers)) || empty($headers)) {
            if(!is_array($data) && ($this->format == 'xml' || $this->format == 'json')) { // for single values 
                $data = array('result'=>$data); 
            } 

            if($this->format == 'json') { // json   
                echo json_encode($data);
            } else if($this->format == 'xml') {
                $xml = new SimpleXMLElement("<?xml version=\"1.0\"?><root></root>");

                $this->arrayToXML($data, $xml);

                // set xml header
                header('Content-Type: text/xml');

                echo $xml->asXML();
            } else if($this->format == 'csv') { // csv
                $this->outputCsv('data.csv', $data);
            } else { // plain text, default
                if(is_array($data)) {
                    echo implode(',', $data);
                } else { 
                    echo $data; // plain text
                }
            }   
        } else {
           echo $data;
        }

        exit;   
    } 

    /**
    * Converts an array to SimpleXML Element, can be a multi dimensional array, duplicate keys are NOT
    * allowed within same object
    *
    * @param array $array Array to be converted
    * @param object $xml XML element to append to
    * @return SimpleXML Element 
    */
    private function arrayToXML($array, &$xml) {
        foreach($array as $key => $value) {
            if(is_array($value)) {
                if(!is_numeric($key)){
                    $subnode = $xml->addChild("$key");
                    $this->arrayToXML($value, $subnode);
                } else {
                    $this->arrayToXML($value, $xml);
                }
            } else {
                $xml->addChild("$key", "$value");
            }
        }
    }

    /**
    * Takes in a filename and an array associative data array and outputs a csv file
    *
    * @param string $fileName
    * @param array $data Array to write to CSV
    * @return void    
    */
    private function outputCsv($fileName, $data) {
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Cache-Control: private', false);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename='.$fileName);

        // check if data is not an array
        if(!is_array($data)) {
            $data = array('result'=>$data);
        }

        // check if data is a single object, if so encase in array
        if(!is_array($data[0])) {
            $data = array($data);
        }

        $fp = fopen('php://output', 'w');

        // header rows
        $headerObj = $data;

        if(!empty($data[0])) {
            $headerObj = $data[0];
        }

        if(!is_array($headerObj)) {
            $headerObj = array('result');
        }

        $headerRows = array_keys($headerObj);
        fputcsv($fp, $headerRows);

        foreach($data as $row) {
            if(!is_array($row)) {
                $row = array('result'=>$row);
            }

            foreach($row as $key => $_row) {
                if(is_array($_row)) {
                    $row[$key] = json_encode($row[$key]);
                }
            }

            fputcsv($fp, $row);
        }

        fclose($fp);
        ob_flush();
    }
}
?>