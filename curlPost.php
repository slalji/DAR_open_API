<?php
$data = array(
    "action"		=> "RegisterDomain",
    "token"             => "AaLc8eNZWsZtWlT9LtT7NUha",
    "authemail"         => "test@exampledomain.com",
    "sld"		=> "domainexample",
    "tld"		=> "com",
    "regperiod"		=> 1,
    "nameserver1"       => "ns1.domainexample.com",
    "nameserver2"       => "ns2.domainexample.com",
    "nameserver3"       => "ns3.domainexample.com",
    "nameserver4"       => "ns4.domainexample.com",
    "nameserver5"       => "ns5.domainexample.com",
    "dnsmanagement"	=> 1,
    "emailforwarding"	=> 1,
    "idprotection"	=> 1,
    "firstname"         => "John",
    "lastname"          => "Doe",
    "companyname"	=> "Company Name",
    "address1"          => "Address 1",
    "address2"          => "Address 2",
    "city"		=> "City",
    "state"             => "ST",
    "country"           => "IT",
    "postcode"          => "12345",
    "phonenumber"	=> "4455677888990",
    "email"             => "user@domainexample.com",
    "adminfirstname"	=> "John",
    "adminlastname"	=> "Doe",
    "admincompanyname"	=> "Company Name",
    "adminaddress1"	=> "Address 1",
    "adminaddress2"	=> "Address 2",
    "admincity"		=> "City",
    "adminstate"	=> "ST",
    "admincountry"	=> "IT",
    "adminpostcode"	=> "12345",
    "adminphonenumber"	=> "4455677888990",
    "adminemail"	=> "admin@domainexample.com",
    "domainfields"      => base64_encode(serialize(array_values(array())
)));
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://billing.extremewebtechnologies.com/domainsResellerAPI/api.php");
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSLVERSION, 3);
curl_setopt($ch, CURLOPT_SSL_CIPHER_LIST, 'SSLv3');
$result = curl_exec($ch);
$res    = json_decode($result, true);
print_r($res);
curl_close($ch);
?>