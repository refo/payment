<?php

return array(
    /*
    |--------------------------------------------------------------------------
    | Garanti
    |--------------------------------------------------------------------------
    |
    | ...
    |
    | --
    |
    */
    'garanti' => array(
        'provuser'   => 'PROVAUT',
        'provpass'   => 'PASSWORD1234',
        'refunduser' => 'PROVRFN',
        'refundpass' => 'PASSWORD1234',

        'user'         => 'EPOS',
        'terminal'     => '10101010',
        'merchant'     => '9876543',
        'tdspassword'  => '12345678',

        'endpoint' => array(
            'xml' => 'https://sanalposprov.garanti.com.tr/VPServlet',
            'tds' => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine',
        ),

        'test' => array(
            'provuser'   => 'PROVAUT',
            'provpass'   => 'PASSWORD1234',
            'refunduser' => 'PROVRFN',
            'refundpass' => 'PASSWORD1234',

            'user'        => 'PROVAUT',
            'terminal'    => '87654321',
            //'terminal'    => '30690168',
            'merchant'    => '7000999',
            'tdspassword' => '12345678',

            'endpoint' => array(
                'xml' => 'https://sanalposprovtest.garanti.com.tr/VPServlet',
                'tds' => 'https://sanalposprovtest.garanti.com.tr/servlet/gt3dengine',
            ),
        ),
    ),


    /*
    |--------------------------------------------------------------------------
    | YapıKredi
    |--------------------------------------------------------------------------
    |
    | ...
    |
    | --
    |
    */
    'yapikredi' => array(
        'tid' => '67676767',
        'mid' => '6787678767',
        'posnetid' => '99999',
        'endpoint' => array(
            'xml' => 'https://www.posnet.ykb.com/PosnetWebService/XML',
            'tds' => 'https://www.posnet.ykb.com/3DSWebService/YKBPaymentService',
        ),
        
        'test' => array(
            'mid' => '6797752273',
            'tid' => '67005689',
            'posnetid' => '9685',
            'endpoint' => array(
                'xml' => 'http://setmpos.ykb.com/PosnetWebService/XML',
                'tds' => 'http://setmpos.ykb.com/3DSWebService/YKBPaymentService',
            ),
        ),        
    ),

    /*
    |--------------------------------------------------------------------------
    | Akbank
    |--------------------------------------------------------------------------
    |
    | ...
    |
    | --
    |
    */
    'akbank' => array(
        'merchant' => '101010101',
        'username' => 'USERNAME',
        'password' => 'PASSWORD1234',
        'endpoint' => array(
            'xml' => 'https://www.sanalakpos.com/fim/api',
            'tds' => 'https://www.sanalakpos.com/fim/est3Dgate',
        ),
        
        'test' => array(
            'merchant' => '100100000',
            'username' => 'AKTESTAPI',
            'password' => 'AKBANK01',
            'endpoint' => array(
                'xml' => 'https://entegrasyon.asseco-see.com.tr/fim/api',
                'tds' => 'https://entegrasyon.asseco-see.com.tr/fim/est3Dgate',
            ),
        ),        
    ), 




);



