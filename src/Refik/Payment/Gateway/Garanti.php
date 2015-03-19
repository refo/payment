<?php namespace Refik\Payment\Gateway;


class Garanti extends Gateway {


    protected $xmlRoot           = 'GVPSRequest';
    protected $xmlAddDeclaration = TRUE;
    protected $xmlFormatOutput   = TRUE; // Tek satır XML String için FALSE

    protected $request = array(
        'Mode'        => 'TEST',
        'Version'     => 'v0.01',
        //'ChannelCode' => '',
        'Terminal'    => array(),
        //* Zorunlu
        'Customer'    => array(
            'IPAddress'    => '127.0.0.1',
            'EmailAddress' => 'eticaret@garanti.com.tr',
        ),
        //*/
        'Card'        => array(
            'Number'     => '',
            'ExpireDate' => '',
            'CVV2'       => '',
        ),
        'Order'       => array(
            'OrderID'     => '',
            //'GroupID'     => '',
            /*
            'AddressList' => array(
                'Address' => array(
                    'Type'        => 'S',
                    'Name'        => '',
                    'LastName'    => '',
                    'Company'     => '',
                    'Text'        => '',
                    'District'    => '',
                    'City'        => '',
                    'PostalCode'  => '',
                    'Country'     => '',
                    'PhoneNumber' => '',
                ),
            ),
            //*/
        ),
        'Transaction' => array(
            'Type'                  => '',
            //'InstallmentCnt'        => '',
            'Amount'                => '', // Kuruş
            'CurrencyCode'          => '949',  // TRL: 949
            //'CardholderPresentCode' => '0',
            //'MotoInd'               => 'N',
            //'OriginalRetrefNum'     => '',
        ),
    );

    protected $currencyCode = array(
        'TRY' => '949',
        'USD' => '840',
        'EUR' => '978',
        'GBP' => '826',
        'JPY' => '392',
    );


    protected function prepareRequest($endpointType = 'xml')
    {

        $endpoint       = $this->config('endpoint');
        $this->endpoint = $endpoint[$endpointType];

        $this->request['Mode'] = $this->test ? 'TEST' : 'PROD';

        parent::prepareRequest();
        $request = $this->requestBody;

        //$request = preg_replace('/\n[ ]+/', "\n", $request);
        //die($request);
        //$this->requestBody = 'xmldata=' . urldecode($this->requestBody);
    }


    protected function makeHash()
    {
        $args = func_get_args();
        $str  = implode('', $args);

        //error_log( var_dump($args) );
        return strtoupper( sha1($str) );
    }


    protected function secureKey($secureKeyField)
    {
        $terminal = str_pad($this->config('terminal'), 9, '0', STR_PAD_LEFT);
        $provpass = $this->config($secureKeyField);
        
        return $this->makeHash($provpass, $terminal);
    }

    protected function appendTerminal()
    {
        $this->request['Terminal'] = [
            'MerchantID' => $this->config('merchant'),
            'ProvUserID' => $this->config('provuser'),
            'UserID'     => $this->config('user'),
            'ID'         => $this->config('terminal'),
        ];
    }

    protected function appendHashData($secureKeyField = 'provpass')
    {
        $this->request['Terminal']['HashData'] = $this->makeHash(
            $this->request['Order']['OrderID'],
            $this->request['Terminal']['ID'],
            $this->request['Card']['Number'],
            $this->request['Transaction']['Amount'],
            $this->secureKey($secureKeyField)
        );
    }

    protected function generateId($len = 8)
    {
        return parent::generateId($len);
    }


    protected function isApproved()
    {
        return (bool)($this->xpath('Response/ReasonCode') == '00' OR $this->xpath('Response/Code') == '00');
    }


    protected function parseExp($exp)
    {
        $exp = parent::parseExp($exp);
        // AAYY
        return $exp['month'] . $exp['year'];
    }


    public function sale($order_id, $card, $amount, $currency = 'TRY', $installmentCount = 0)
    {
        if ($order_id == NULL) $order_id = $this->generateId();
        $amount       = $this->parseAmount($amount);
        $ccNo         = $this->parseCCNo($card['no']);
        $ccExp        = $this->parseExp($card['exp']);
        $ccCVC        = $this->getVal($card, 'cvc', 'cvv', 'cvc2', 'cvv2');
        $currencyCode = $this->currencyCode[$currency];

        $this->request['Transaction']['Type']         = 'sales';
        $this->request['Transaction']['Amount']       = $amount;
        $this->request['Transaction']['CurrencyCode'] = $currencyCode;

        $this->request['Order']['OrderID'] = $order_id;
        
        $this->request['Card'] = array(
            'Number'     => $ccNo,
            'ExpireDate' => $ccExp,
            'CVV2'       => $ccCVC,
        );

        if ($installmentCount > 0) {
            $this->request['Transaction']['InstallmentCnt'] = $installmentCount;
        }

        $this->appendTerminal();
        $this->appendHashData();
        $this->performTransaction();

        $return             = $this->saleResponse();
        $return['amount']   = $amount / 100;
        $return['currency'] = $currency;
        return $return;
    }


    protected function saleResponse()
    {
        $approved = $this->isApproved();

        $return = array(
            'approved'        => $approved,
            'success'         => $approved,
            'order_id'        => $this->request['Order']['OrderID'],
            'gateway_name'    => $this->gatewayName,
            'bank_response'   => $this->responseXMLString,
            'authCode'        => $this->xpath('Transaction/AuthCode'),
            'transactionNo'   => $this->xpath('Transaction/RetrefNum'),
            'transactionType' => 'sale',
            'error'           => $this->xpath('Response/ReasonCode'),
            'error_message'   => $this->xpath('Response/SysErrMsg'),
        );

        return $return;
    }


    public function void($order_id, $transactionNo)
    {
        $this->request['Transaction']['Type']   = 'void';
        $this->request['Transaction']['Amount'] = '1';
        $this->request['Transaction']['OriginalRetrefNum'] = $transactionNo;
        
        $this->request['Order']['OrderID'] = $order_id;

        $this->request['Terminal'] = [
            'MerchantID' => $this->config('merchant'),
            'ProvUserID' => $this->config('refunduser'), // Refund user password
            'UserID'     => $this->config('user'),
            'ID'         => $this->config('terminal'),
        ];


        $this->appendHashData('refundpass');
        $this->performTransaction();
        return $this->voidResponse();
    }


    protected function voidResponse()
    {
        $approved = $this->isApproved();

        $return = array(
            'approved'        => $approved,
            'success'         => $approved,
            'order_id'      => $this->request['Order']['OrderID'],
            'gateway_name'    => $this->gatewayName,
            'bank_response'   => $this->responseXMLString,
            'authCode'        => $this->xpath('Transaction/AuthCode'),
            'transactionNo'   => $this->xpath('Transaction/RetrefNum'),
            'transactionType' => 'void',
            'error'           => $this->xpath('Response/ReasonCode'),
            'error_message'   => $this->xpath('Response/ErrorMsg'),
        );

        return $return;
    }


    public function refund($order_id, $transactionNo, $amount, $currencyCode)
    {
        $amount = $this->parseAmount($amount);
        $this->request['Transaction']['Type']   = 'refund';
        $this->request['Transaction']['Amount'] = $amount;
        $this->request['Transaction']['OriginalRetrefNum'] = $transactionNo;
        
        $this->request['Order']['OrderID'] = $order_id;

        $this->request['Terminal'] = [
            'MerchantID' => $this->config('merchant'),
            'ProvUserID' => $this->config('refunduser'), // Refund user password
            'UserID'     => $this->config('user'),
            'ID'         => $this->config('terminal'),
        ];


        $this->appendHashData('refundpass');
        $this->performTransaction();
        $return = $this->voidResponse();
        $return['transactionType'] = 'refund';
        $return['amount'] = $amount / 100;
        return $return;
    }



}



