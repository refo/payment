<?php namespace Refik\Payment\Gateway;

class Akbank extends Gateway
{

    protected $xmlRoot           = 'CC5Request';
    protected $xmlAddDeclaration = false;
    protected $xmlFormatOutput   = true; // Tek satır XML String için FALSE

    protected $request = [
        'Name'     => '',
        'Password' => '',
        'ClientId' => '',
        'Type'     => '',
    ];

    protected $currencyCode = [
        'TRY' => '949',
        'USD' => '840',
        'EUR' => '978',
        'GBP' => '826',
        'JPY' => '392',
        'RUB' => '643',
    ];

    protected function parseAmount($amount)
    {
        $amount = parent::parseAmount($amount);
        return sprintf('%01.2F', (int)$amount/100);
    }

    protected function parseExp($exp)
    {
        $exp = parent::parseExp($exp);
        return sprintf('%s/20%s', $exp['month'], $exp['year']);
    }

    //Approved
    protected function isApproved()
    {
        return (bool) ($this->xpath('Response') == 'Approved');
    }

    protected function prepareRequest($endpointType = 'xml')
    {
        $endpoint       = $this->config('endpoint');
        $this->endpoint = $endpoint[$endpointType];

        $this->request['Name']     = $this->config('username');
        $this->request['Password'] = $this->config('password');
        $this->request['ClientId'] = $this->config('merchant');

        parent::prepareRequest();
        $this->requestBody = 'data='.urldecode($this->requestBody);
    }

    public function sale($order_id, $card, $amount, $currency = 'TRY', $installmentCount = 0)
    {
        if ($order_id == null) {
            $order_id = $this->generateId();
        }
        $_amount      = $this->parseAmount($amount);
        $ccNo         = $this->parseCCNo($card['no']);
        $ccExp        = $this->parseExp($card['exp']);
        $ccCVC        = $this->getVal($card, 'cvc', 'cvv', 'cvc2', 'cvv2');
        $currencyCode = $this->currencyCode[$currency];

        $this->request['Type']     = 'Auth';
        $this->request['OrderId']  = $order_id;
        $this->request['Total']    = $_amount;
        $this->request['Currency'] = $currencyCode;
        $this->request['Number']   = $ccNo;
        $this->request['Expires']  = $ccExp;
        $this->request['Cvv2Val']  = $ccCVC;

        if ($installmentCount > 0) {
            $this->request['Instalment'] = $installmentCount;
        }

        $this->performTransaction();

        $return               = $this->saleResponse();
        $return['instalment'] = $installmentCount;
        $return['amount']     = $amount;
        $return['currency']   = $currency;

        return $return;
    }

    protected function saleResponse()
    {
        $approved = $this->isApproved();

        $return = array(
            'approved'        => $approved,
            'success'         => $approved,
            'order_id'        => $this->request['OrderId'],
            'gateway_name'    => $this->gatewayName,
            'bank_response'   => $this->responseXMLString,
            'authCode'        => $this->xpath('AuthCode'),
            'transactionNo'   => $this->xpath('TransId'),
            'transactionType' => 'sale',
            'error'           => $this->xpath('Extra/NUMCODE'),
            'error_message'   => $this->xpath('ErrMsg'),
        );

        return $return;
    }

    public function void($order_id, $transactionNo)
    {
        $this->request['Type']     = 'Void';
        $this->request['OrderId']  = $order_id;
        //$this->request['TransId']  = $transactionNo;

        $this->performTransaction();

        return $this->voidResponse();
    }

    protected function voidResponse()
    {
        $approved = $this->isApproved();

        $return = array(
            'approved'        => $approved,
            'success'         => $approved,
            'gateway_name'    => $this->gatewayName,
            'bank_response'   => $this->responseXMLString,
            'authCode'        => $this->xpath('AuthCode'),
            'transactionNo'   => $this->xpath('TransId'),
            'transactionType' => 'void',
            'error'           => $this->xpath('Extra/NUMCODE'),
            'error_message'   => $this->xpath('ErrMsg'),
        );

        return $return;
    }

    public function refund($order_id, $transactionNo, $amount, $currencyCode)
    {
        $this->request['Type']     = 'Credit';
        $this->request['OrderId']  = $order_id;
        $this->request['TransId']  = $transactionNo;
        $this->request['Total']    = $amount;
        $this->request['Currency'] = $this->currencyCode[$currency];

        $this->performTransaction();
        return $this->voidResponse();
    }

}
