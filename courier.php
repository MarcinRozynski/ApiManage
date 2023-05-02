<?php

class Courier
{
    private const URL = 'http://mtapi.net/?testMode=0';
    private const API_KEY = 'f16753b55cac6c6e';

    private static array $mapSenderToConsignorAddress = [
        'sender_company'    => 'Company',
        'sender_fullname'   => 'Name',
        'sender_address'    => 'AddressLine1',
        'sender_city'       => 'City',
        'sender_country'    => 'Country',
        'sender_postalcode' => 'Zip',
        'sender_email'      => 'Email',
        'sender_phone'      => 'Phone',
        'ConsignorAddress'  => 'ConsignorAddress',
    ];

    private static array $mapDeliveryToConsigneeAddress = [
        'delivery_company'  => 'Company',
        'delivery_fullname' => 'Name',
        'delivery_address'  => 'AddressLine1',
        'delivery_city'     => 'City',
        'delivery_postalcode' => 'Zip',
        'delivery_country'  => 'Country',
        'delivery_email'    => 'Email',
        'delivery_phone'    => 'Phone',
    ];

    private static array $characterLimits = [
        'PPTT' => [
            'OrderShipment' => 30,
            'Name'          => 30,
            'Company'       => 30,
            'AddressLine1'  => 30,
            'AddressLine2'  => 30,
            'AddressLine3'  => 30,
            'City'          => 30,
            'State'         => 30,
            'Zip'           => 20,
            'Weight'        => 2,
            'DisplayId'     => 15,
            'Description'   => 20,
            'HsCode'        => 25,
            'Phone'         => 15,
        ],
    ];

    // Method to mapping array keys from spring.php to valid data for API
    public function mapDataKeys(array $order, array $params): array
    {
        foreach (self::$mapSenderToConsignorAddress as $index => $oldFieldName) {
            if (isset($order[$index]) && !empty($order[$index])) {
                $shipment['ConsignorAddress'][$oldFieldName] = $order[$index];
            }
        }

        foreach (self::$mapDeliveryToConsigneeAddress as $index => $oldFieldName) {
            if (isset($order[$index]) && !empty($order[$index])) {
                $shipment['ConsigneeAddress'][$oldFieldName] = $order[$index];
            }
        }

        return $this->characterLimits($shipment, $params['service']);
    }

    // That method creates new packages for spring broker
    public function newPackage(array $order, array $params): string
    {
        try {
            if (!$this->isServiceAllowed($params['service'])) {
                throw new Exception('You have no access to this Service');
            }

            $bodyParameters['Shipment'] = [
                'ShipperReference' => '',
                'Service' => $params['service'],
                "Weight" => "0.85",
                "Value" => "20",
                "Currency" => "EUR",
                "Description" => "CD",
                "ConsignorAddress" => $order['ConsignorAddress'],
                "ConsigneeAddress" => $order['ConsigneeAddress'],
            ];

            return $this
                ->requestAPI('OrderShipment', $bodyParameters)
                ->Shipment
                ->TrackingNumber;
        } catch (Exception $e) {
            echo $e->getMessage();
            die;
        }
    }

    // Method generate, display, and delate file from local disc
    public function packagePDF(string $trackingNumber)
    {
        try {
            $pdf_base64 = $this
                ->requestAPI('GetShipmentLabel', ["Shipment" => ["TrackingNumber" => $trackingNumber]])
                ->Shipment
                ->LabelImage;

            $bin = base64_decode($pdf_base64, true);
            if (strpos($bin, '%PDF') !== 0) {
                throw new Exception('Missing the PDF file signature');
            }

            $fileName = time() . uniqid() . '.pdf';

            file_put_contents($fileName, $bin);

            header("Content-type: application/pdf");
            header("Content-Disposition: inline; filename=" . $fileName . "");
            readfile($fileName);

            if (file_exists($fileName)) {
                unlink($fileName);
            }
        } catch (Exception $e) {
            echo $e->getMessage();
            die;
        }
    }

    // API Request handler
    private function requestAPI(string $command, array $params)
    {
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => 'Content-type: application/json',
                'content' => json_encode([
                    "Command" => $command,
                    'Apikey' => self::API_KEY,
                    ...$params
                ]),
            ],
        ];

        $context = stream_context_create($options);

        $result = file_get_contents(self::URL, false, $context);

        if ($result === false) {
            throw new Exception(error_get_last()['message']);
        }

        $result = json_decode($result);

        if ($result->ErrorLevel === 10) {
            throw new Exception($result->Error);
        }

        return $result;
    }

    // Method to check if Specified service is allowed for user
    private function isServiceAllowed(string $service): bool
    {
        $services = $this->requestAPI('GetServices', ['Apikey' => self::API_KEY]);

        foreach ($services->Services->AllowedServices as $allowedService) {
            if ($service === $allowedService) {
                return true;
            }
        }
        return false;
    }

    // Handling characters limits
    private function characterLimits(array $fields, string $service): array
    {
        foreach (self::$characterLimits[$service] as $name => $characterLimit) {
            if (isset($fields['ConsignorAddress'][$name]) && strlen(
                    $fields['ConsignorAddress'][$name]
                ) > $characterLimit) {
                $fields['ConsignorAddress'][$name] = substr(
                    $fields['ConsignorAddress'][$name],
                    0,
                    $characterLimit
                );
            } elseif (isset($fields['ConsigneeAddress'][$name]) && strlen(
                    $fields['ConsigneeAddress'][$name]
                ) > $characterLimit) {
                $fields['ConsigneeAddress'][$name] = substr(
                    $fields['ConsigneeAddress'][$name],
                    0,
                    $characterLimit
                );
            }
        }

        return $fields;
    }
}
