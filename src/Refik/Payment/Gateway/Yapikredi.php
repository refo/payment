<?php namespace Refik\Payment\Gateway;


class Yapikredi extends Gateway {


    protected $xmlRoot   = 'posnetRequest';
    //protected $xmlAddDeclaration = TRUE;
    protected $httpVersion = '1.0';
    protected $xmlEncoding = 'ISO-8859-9';

    protected $request = array(
        'mid' => '',
        'tid' => '',
    );

    protected $currencyCode = array(
        'TRY' => 'YT',
        'USD' => 'US',
        'EUR' => 'EU',
    );


    protected function prepareRequest($endpointType = 'xml')
    {
        $this->request['mid'] = $this->config('mid');
        $this->request['tid'] = $this->config('tid');
        //$this->request['tranDateRequired'] = '1';

        $endpoint       = $this->config('endpoint');
        $this->endpoint = $endpoint[$endpointType];

        parent::prepareRequest();

        $this->requestBody = 'xmldata=' . urldecode($this->requestBody);
    }


    /**
     * 
     */
    protected function generateId($len = 24)
    {
        return (string)parent::generateId($len);

        /*
        $timeAsSortableNumber = \TB\Helper::ts(time(), 'YmdHis');
        $hash   = (string)\TB\Helper::generateId(1);
        $padLen = $len - strlen($hash) - 3;
        $padInd = str_pad($padLen, 2, '0', STR_PAD_LEFT);
        $pad    = str_repeat('0', $padLen);

        return $padInd . $pad . '-' . $hash;
        //*/
    }


    public function sale($order_id, $card, $amount, $currency = 'TRY', $installmentCount = 0)
    {
        if ($order_id == NULL) $order_id = $this->generateId();
        $amount       = $this->parseAmount($amount);
        $ccNo         = $this->parseCCNo($card['no']);
        $ccExp        = $this->parseExp($card['exp']);
        $ccCVC        = $this->getVal($card, 'cvc', 'cvv', 'cvc2', 'cvv2');
        $currencyCode = $this->currencyCode[$currency];

        $sale = array(
            'orderID'      => $order_id,
            'amount'       => $amount,
            'ccno'         => $ccNo,
            'currencyCode' => $currencyCode,
            'cvc'          => $ccCVC,
            'expDate'      => $ccExp,
        );

        if ($installmentCount > 0) {
            $sale['installment'] = $installmentCount;
        }

        $this->request['sale'] = $sale;

        $this->performTransaction();
        return $this->saleResponse();
    }


    protected function saleResponse()
    {
        $approved = (bool)($this->xpath('approved') === '1');

        $return = array(
            'approved'      => $approved,
            'success'       => $approved,
            'order_id'      => $this->request['sale']['orderID'],
            'gateway_name'  => $this->gatewayName,
            'bank_response' => $this->responseXMLString,
            'authCode'      => $this->xpath('authCode'),
            'transactionNo' => $this->xpath('hostlogkey'),
            'error'         => $this->xpath('respCode'),
            'error_message' => $this->xpath('respText'),
        );

        return $return;
    }


    /**
     * İPTAL işlemi
     * 
     *  TODO:
     *  - Sadece 'sale' türündeki işlemleri iptal ediyor.
     *    İşlemden türünden bağımsız olarak çalışmalı.
     *    Argument olarak işlem türü istenebilir.
     *    
     *    
     */
    public function void($order_id, $transactionNo)
    {
        $reverse = array(
            'transaction' => 'sale',
            'hostLogKey'  => $transactionNo,
        );

        $this->request['reverse'] = $reverse;

        $this->performTransaction();
        return $this->voidResponse();
    }


    /**
     * voidResponse
     * Generic cevapları döndürmek için kullanılabilir
     * 
     *  - saleResponse 'dan farklı olarak, order_id cevaba dahil değil
     *  - order_id, request array'inde, order_id döndürmek için farklı
     *    bir yol bulunabilir.
     * 
     * @return Array Response array
     */
    protected function voidResponse()
    {
        $approved = (bool)($this->xpath('approved') === '1');

        $return = array(
            'approved'      => $approved,
            'success'       => $approved,
            'gateway_name'  => $this->gatewayName,
            'bank_response' => $this->responseXMLString,
            'authCode'      => $this->xpath('authCode'),
            'transactionNo' => $this->xpath('hostlogkey'),
            'error'         => $this->xpath('respCode'),
            'error_message' => $this->xpath('respText'),
        );

        return $return;
    }


    /**
     * 
     */
    public function refund($order_id, $transactionNo, $amount, $currencyCode)
    {
        $return = array(
            'amount'       => $amount,
            'hostLogKey'   => $transactionNo,
            'currencyCode' => $this->currencyCode[$currencyCode],
        );

        $this->request['return'] = $return;

        $this->performTransaction();
        return $this->voidResponse();
    }


    public function saleTds($order_id, $card, $amount, $currency = 'TRY', $installmentCount = 0, $cardHolder = 'OSMAN')
    {
        if ($order_id == NULL) $order_id = $this->generateId(20);
        $amount       = $this->parseAmount($amount);
        $ccNo         = $this->parseCCNo($card['no']);
        $ccExp        = $this->parseExp($card['exp']);
        $ccCVC        = $this->getVal($card, 'cvc', 'cvv', 'cvc2', 'cvv2');
        $currencyCode = $this->currencyCode[$currency];

        $oos = array(
            'cardHolderName' => $cardHolder,
            'XID'            => $order_id,
            'ccno'           => $ccNo,
            'expDate'        => $ccExp,
            'cvc'            => $ccCVC,
            'amount'         => $amount,
            'currencyCode'   => $currencyCode,
            'posnetid'       => $this->config('posnetid'),
            'installment'    => $installmentCount,
            'tranType'       => 'Sale',
        );

        if ($installmentCount > 0) {
            $oos['installment'] = $installmentCount;
        }

        $this->request['oosRequestData'] = $oos;

        $this->performTransaction('tds');
        //return $this->responseXMLString;
        return $this->saleTdsResponse();
    }


    public function saleTdsResponse()
    {
        $approved = (bool)($this->xpath('approved') === '1');

        $form = array(
            'mid' => $this->config('mid'),
            'posnetID' => $this->config('posnetid'),
            'posnetData'  => $this->xpath('data1'),
            'posnetData2' => $this->xpath('data2'),
            'digest'  => $this->xpath('sign'),
            'vftCode' => '00',
            'lang'    => 'tr',
            'url'     => '',
            'merchantReturnURL' => '',
            'openANewWindow'    => '1',
        );

        $return = array(
            'approved'      => $approved,
            'success'       => $approved,
            'gateway_name'  => $this->gatewayName,
            'bank_response' => $this->responseXMLString,
            'tds'           => $form,
            'error'         => $this->xpath('respCode'),
            'error_message' => $this->xpath('respText'),
        );

        return $return;
    }


    /**
     * 
     */
    public function saleTdsCallback($input = NULL)
    {
        if ($input === NULL) {
            $input = Input::only('BankPacket');
        }

        $tdsTransaction = array(
            'bankData' => $input['BankPacket'],
            'wpAmount' => '0',
        );

        $this->request['oosTranData'] = $tdsTransaction;

        $this->performTransaction('tds');
        return $this->saleTdsResponse();
    }

    


}



