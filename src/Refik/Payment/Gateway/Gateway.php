<?php namespace Refik\Payment\Gateway;

use Unirest\Request as Unirest;
use Hashids\Hashids;
use SimpleXMLElement;
use ReflectionClass;
use DOMDocument;

abstract class Gateway {

    public $test = FALSE;

    protected $gatewayName;

    protected $endpoint  = '';
    protected $xmlRoot   = '';
    protected $xmlAddDeclaration = TRUE;
    protected $xmlFormatOutput = TRUE; // Tek satır string için "FALSE"
    
    protected $config = array();
    protected $requestXML;
    protected $request          = array();
    protected $requestXMLString = '';
    protected $requestBody      = '';

    protected $responseXML;
    protected $responseXMLString = '';

    protected $xmlEncoding       = 'UTF-8';
    
    protected $httpVersion       = '1.1';


    /**
     * Constructor
     */
    public function __construct()
    {
        Unirest::verifyPeer(FALSE);
        //Unirest::httpVersion($this->httpVersion);

        $ref = new ReflectionClass($this);
        $this->gatewayName = strtolower( $ref->getShortName() );
    }


    /**
     * Initialize
     * @param  Array  $config Should include a 'test' key
     * 'test' key should be an array of test configuration.
     * @return void
     */
    public function init(Array $config)
    {
        $this->config = $config;
    }


    /**
     * 
     */
    protected function config($key = NULL)
    {
        $c = $this->test ? $this->config['test'] : $this->config;
        if ($key === NULL) return $c;
        if ( isset($c[$key]) ) return $c[$key];
    }


    /**
     * Prepare request body
     *
     * Şunları hazırla:
     * $this->requestXML
     * $this->requestXMLString
     * $this->requestBody
     * 
     * @return void
     */
    protected function prepareRequest()
    {
        $this->createXMLFromRequestArray();
        $this->requestBody = $this->requestXMLString;
    }


    /**
     * [performTransaction description]
     * @return [type] [description]
     */
    protected function performTransaction()
    {
        $this->prepareRequest();

        $unirestResponse = Unirest::post(
            $this->endpoint,
            array(),
            $this->requestBody
        );
        $this->responseXMLString = $unirestResponse->body;
        $this->responseXML       = simplexml_load_string($this->responseXMLString);
    }


    /**
     * requestXML ve requestXMLString attribute'lerini doldurur
     * aşağıdaki dönüştürmeleri yapar:
     * 
     * Array $this->request                to  SimpleXMLElement $this->requestXML
     * SimpleXMLElement $this->requestXML  to  String $this->requestXMLString
     *
     * Daha sonra şu attribute'ler kullanılabilir olacaktır:
     * $this->requestXML
     * $this->requestXMLString
     * 
     * @return void
     */
    protected function createXMLFromRequestArray()
    {
        $this->requestXML = $this->xmlFromArray($this->xmlRoot, $this->request, $this->xmlEncoding);
        $this->requestXMLString = $this->xmlStringFromXML($this->requestXML);
    }


    /**
     * Verilen array'i recursive olarak gezip XML nesnesi oluşturur.
     * 
     * @param  String $root     XML nesnesi için root tag
     * @param  [type] $arr      XML'e dönüştürülecek [recursive] array
     * @param  string $encoding XML encoding
     * @return SimpleXMLElement Dönüştürülmüş XML nesnesi
     */
    protected function xmlFromArray($root, $arr, $encoding = 'UTF-8')
    {
        $addChild = function ($xml, $child) use (& $addChild)
        {
            foreach( $child as $key => $val)
            {
                if ( is_array($val) OR is_object($val) ) {
                    $addChild( $xml->addChild($key), $val );
                } else {
                    $xml->addChild($key, $val);
                }
            }
        };

        $root = sprintf('<?xml version="1.0" encoding="%s"?><%s/>', $encoding, $root);
        $xml  = new SimpleXMLElement($root, LIBXML_NOEMPTYTAG);

        $addChild($xml, $arr);

        return $xml;
    }


    /**
     * XML nesnesini, string'e dönüştürür.
     * 
     * @param  SimpleXMLElement $xml Dönüştürülecek XML nesnesi
     * @return String                String olarak sunulmuş XML
     */
    protected function xmlStringFromXML(SimpleXMLElement $xml)
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = FALSE;
        $dom->formatOutput = $this->xmlFormatOutput;  // Tek satır string için "FALSE"
        $dom->loadXML($xml->asXML() );

        //
        // DOMDocument::saveXML(node, LIBXML_NOEMPTYTAG)
        // LIBXML_NOEMPTYTAG: Prevent self-closing tags
        // 
        if ($this->xmlAddDeclaration) {
            return $dom->saveXML($dom, LIBXML_NOEMPTYTAG);
        } else {
            return $dom->saveXML($dom->documentElement, LIBXML_NOEMPTYTAG);
        }
    }


    /**
     * Perform a xpath query on $this->responseXML
     * @param  String $path Query path will be prefixed with '//'
     * @return String       Query result will be casted to string
     */
    protected function xpath($path)
    {
        // prefix $path to root
        $path = '//'.$path;
        $xml = $this->responseXML;
        if (
            is_array($xml->xpath($path)) AND
            isset($xml->xpath($path)[0])
        ) {
            return (string)$xml->xpath($path)[0];
        } else {
            return NULL;
        }
    }


    /**
     * 
     */
    protected function getVal($obj)
    {
        $args = func_get_args();
        array_shift($args);

        foreach($args as $key) {
            if ( is_array($obj) ){
                if (isset($obj[$key]) ) return $obj[$key];
            } elseif( is_object($obj)){
                if (isset($obj->{$key}) ) return $obj->{$key};
            }
        }

        return NULL;
    }


    /**
     * 
     */
    protected function generateId($minLen = 6)
    {
        $salt     = 'Muh1tt1n Ne 3tT1n';
        $alphabet = '0123456789ACDEFGHJKLMNPRTUVXYZ';
        $hash     = new Hashids($salt, $minLen, $alphabet);
        $mtime    = time() - 1420092000; // Thu, 01 Jan 2015 06:00:00
        return $hash->encode($mtime);
    }


    /**
     * 
     */
    protected function parseAmount($amount)
    {
        $amount = preg_replace('/[^0-9,.]/', '', $amount);
        $amount = str_replace(array('.',','), '.', $amount);
        $amount = $amount * 100;
        
        return (int)$amount;
    }


    /**
     * 
     */
    protected function parseCCNo($ccNo)
    {
        // Nümerik olmayan tüm karakterleri çıkar
        $ccNo = preg_replace('/[^0-9]/', '', $ccNo);

        // TODO:
        // Sonuç, kredi kartı numarası formatına uymuyorsa throw exception

        return $ccNo;
    }


    /**
     * 
     */
    protected function parseExp($exp)
    {
        // TODO:
        // Önceki class'dan hiç el değmeden aldım
        // 

        $format_exp = function($exp, $delim) {
            $exp   = explode($delim, $exp, 2);
            $exp_m = trim($exp[0]);
            $exp_m = str_pad($exp_m, 2, '0', STR_PAD_LEFT);

            $exp_y = trim($exp[1]);
            $exp_y = substr($exp_y, -2);
            
            
            //$exp   = $exp_y . $exp_m;
            //return $exp;
            return array(
                'month' => $exp_m,
                'year'  => $exp_y,
            );
        };
        
        $exp = trim($exp);
        
        switch(TRUE) {
            case strpos($exp, '/') > 0:
                $exp = $format_exp($exp, '/');
                break;
            case strpos($exp, ' ') > 0:
                $exp = $format_exp($exp, ' ');
                break;
            case strlen($exp) == 4:
                return array(
                    'month' => substr($exp, 0, 2),
                    'year'  => substr($exp, -2),
                );
                break;
            default:
                // Hata
                $exp = NULL;
                break;
        }

        return $exp;
    }


}



