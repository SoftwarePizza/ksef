<?php

class KsefXml
{
    private $order;
    private $invoice;
    private $seller;
    private $buyer;
    private $lines;

    public function __construct($order, $invoice)
    {
        $this->order = $order;
        $this->invoice = $invoice;
    }

    public function generate()
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><KsefInvoice xmlns="http://crd.gov.pl/wzor/2023/06/29/12648/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://crd.gov.pl/wzor/2023/06/29/12648/ schemat.xsd"></KsefInvoice>');

        $this->addHeader($xml);
        $this->addSeller($xml);
        $this->addBuyer($xml);
        $this->addBody($xml);

        return $xml->asXML();
    }

    private function addHeader($xml)
    {
        $header = $xml->addChild('Naglowek');
        $header->addChild('KodFormularza', 'FA');
        $header->addChild('WariantFormularza', '2');
        $header->addChild('DataWytworzeniaFa', date('Y-m-d\TH:i:s\Z'));
    }

    private function addSeller($xml)
    {
        $seller = $xml->addChild('Podmiot1');
        $dane = $seller->addChild('DaneIdentyfikacyjne');
        $dane->addChild('NIP', Configuration::get('KSEF_NIP'));
        $dane->addChild('Nazwa', Configuration::get('PS_SHOP_NAME'));
        
        $adres = $seller->addChild('Adres');
        $adres->addChild('KodKraju', 'PL');
        $adres->addChild('AdresL1', Configuration::get('PS_SHOP_ADDR1') . ' ' . Configuration::get('PS_SHOP_ADDR2'));
        $adres->addChild('AdresL2', Configuration::get('PS_SHOP_CITY') . ' ' . Configuration::get('PS_SHOP_CODE'));
    }

    private function addBuyer($xml)
    {
        $buyerAddress = new Address($this->order->id_address_invoice);
        $customer = new Customer($this->order->id_customer);

        $buyer = $xml->addChild('Podmiot2');
        $dane = $buyer->addChild('DaneIdentyfikacyjne');
        
        if (!empty($buyerAddress->vat_number)) {
            $nip = preg_replace('/[^0-9]/', '', $buyerAddress->vat_number);
            $dane->addChild('NIP', $nip);
        } else {
            $dane->addChild('BrakID', '1');
        }
        
        $dane->addChild('Nazwa', $buyerAddress->company ? $buyerAddress->company : $buyerAddress->firstname . ' ' . $buyerAddress->lastname);

        $adres = $buyer->addChild('Adres');
        $adres->addChild('KodKraju', 'PL'); // Simplified, should check address country
        $adres->addChild('AdresL1', $buyerAddress->address1 . ' ' . $buyerAddress->address2);
        $adres->addChild('AdresL2', $buyerAddress->city . ' ' . $buyerAddress->postcode);
    }

    private function addBody($xml)
    {
        $fa = $xml->addChild('Fa');
        $fa->addChild('KodWaluty', 'PLN'); // Simplified
        $fa->addChild('P_1', date('Y-m-d', strtotime($this->order->date_add)));
        $fa->addChild('P_2', $this->invoice->number);
        $fa->addChild('P_15', $this->order->total_paid_tax_incl);

        // Lines
        $products = $this->order->getProducts();
        foreach ($products as $product) {
            $line = $fa->addChild('FaWiersz');
            $line->addChild('NrWierszaFa', $product['product_id']);
            $line->addChild('P_7', $product['product_name']);
            $line->addChild('P_8A', 'szt');
            $line->addChild('P_8B', $product['product_quantity']);
            $line->addChild('P_9A', $product['unit_price_tax_excl']);
            $line->addChild('P_11', $product['total_price_tax_excl']);
            
            // VAT Rate mapping would go here
            $line->addChild('P_12', '23'); // Defaulting to 23 for now
        }
    }
}
