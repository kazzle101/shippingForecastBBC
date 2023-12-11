<?php
include_once("app/config.class.php");
include_once("app/utilities.class.php");
include_once("app/makerequest.class.php");
include_once("app/database.class.php");
include_once("app/navigation.class.php");
include_once("app/jsoncache.class.php");

class ShippingForecast {

    private $api;

    public function __construct() {
        $this->api = $this->apiSetup();
    }

    private function apiSetup() {
        $a = new stdClass();
        $a->Endpoint = "https://www.bbc.co.uk/weather/coast-and-sea/shipping-forecast";
        return $a;
    }

    private function getBBCpage() {

        $curl = curl_init($this->api->Endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $content = curl_exec($curl);

        if ($content !== false) {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true); // Ignore HTML errors
            $dom->loadHTML($content);
            $dom;
        } else {
            $dom = null;
        }

        curl_close($curl);

        return $dom;

    }

    // normally in utilities.class.php
    private function errorMessage($id, $message, $status="error") {

        $data = new stdClass();
        $data->status = $status;
        $data->id = $id;
        $data->message = $message;

        return $data;
    }

    private function sfDatetoObj($d) {

        $d = strip_tags($d);
        $d = str_replace("From: ","", $d);
        $d = str_replace("To: ","", $d);
        $d = str_replace("at ","", $d);
        return DateTime::createFromFormat("D jS M H:i e", trim($d));  // Sun 10th Dec at 12:00 UTC
    }

    public function makeShippingData($htmlDom) {
        $utils = new Utilities();
        $data = new stdClass();

        $xpath = new DOMXPath($htmlDom);
        $svgElements = $xpath->query("//svg");
        foreach ($svgElements as $svgElement) {
            $svgElement->parentNode->removeChild($svgElement);
        }


        $dates = new stdClass();
        $dates->from = false;
        $dates->to = false;
        $fromDom = $xpath->query("//p[contains(@class, 'wr-c-coastandsea-validity__from')]");
        if ($fromDom->length > 0) {
            $fromVal = $fromDom[0]->nodeValue; 
            $dates->from = $this->sfDatetoObj($fromVal);
        }
        $toDom = $xpath->query("//p[contains(@class, 'wr-c-coastandsea-validity__to')]");
        if ($toDom->length > 0) {
            $toVal = $toDom[0]->nodeValue;
            $dates->to = $this->sfDatetoObj($toVal);
        }
        if (!$dates->from || !$dates->to) {
            return $this->errorMessage("MSD001", "error: cannot decode shipping forecast");
        }

        $summaryWarnings = new stdClass();
        $summary = new stdClass();
        $summaryDom = $xpath->query("//section[contains(@class, 'wr-c-coastandsea-summary')]");
        if ($summaryDom->length > 0) {
            $warningsDiv = $xpath->query(".//div[contains(@class, 'wr-c-coastandsea-summary__warnings')]//p");
            $summaryWarnings->title = $warningsDiv[0]->nodeValue;

            $areas = [];
            $listAreas = $xpath->query(".//div[contains(@class, 'wr-c-coastandsea-summary__warnings')]//ul/li");
            if ($listAreas->length > 0) {
                foreach ($listAreas as $item) {
                    $areas[] = trim($item->nodeValue);
                }
            }
            $summaryWarnings->areas = $areas;

            $issuedAtNode = $xpath->query(".//div[contains(@class, 'wr-c-coastandsea-summary__warnings')]//p[contains(@class, 'wr-c-coastandsea-summary__warnings__issued-at')]");
            if ($issuedAtNode->length > 0) {
                $summaryWarnings->issuedAt = str_replace("Issued at: ","",$issuedAtNode[0]->nodeValue);
            }



            $summaryTitleNode = $xpath->query(".//h2[contains(@class, 'wr-c-coastandsea-summary__title')]");
            if ($summaryTitleNode->length > 0) {
                $summary->summary = $summaryTitleNode[0]->nodeValue;
            }
            $summaryTextNode = $xpath->query(".//p[contains(@class, 'gel-pica')]");
            if ($summaryTextNode->length > 0) {
                $summary->summaryText = $summaryTextNode[0]->nodeValue;
            }
            $issuedAtNode = $xpath->query(".//p[contains(@class, 'wr-c-issued-at')]//span[contains(@class, 'wr-c-issued-at__time-text')]");
            if ($issuedAtNode->length > 0) {
                $summary->issuedAt = str_replace("Issued at: ","",$issuedAtNode[0]->nodeValue);
            }
        }
        
        $seaAreas = [];
        $areaList = [];
        $id = 1;
        $seaAreasDom = $xpath->query("//section[contains(@class, 'wr-c-coastandsea-regions')]");
        if ($seaAreasDom->length > 0) {
            $seaAreaElements = $xpath->query(".//section[contains(@class, 'wr-c-coastandsea-regions')]//div[contains(@class, 'wr-c-coastandsea-region')]");

            foreach ($seaAreaElements as $seaAreaElement) {
                $area = new stdClass();
                $area->seaArea = $xpath->evaluate("string(.//h3[contains(@class, 'gel-double-pica-bold')])", $seaAreaElement);

                if (in_array($area->seaArea, $areaList)) {
                    continue;  // too lazy to work out how to stop the double $area->seaArea thing
                }
                $areaList[] = $area->seaArea;

                // Extract Warning Title
                $wTitle = $xpath->evaluate("string(.//div[contains(@class, 'wr-c-coastandsea-warnings-banner__bar')])", $seaAreaElement);
                if ($wTitle) {
                    $warnings = new stdClass();
                    $warnings->title = $wTitle;
                    $warnings->text = $xpath->evaluate("string(.//div[contains(@class, 'wr-c-coastandsea-summary__warnings')]/div/p[1])", $seaAreaElement);
                    $warningIssuedAt = $xpath->evaluate("string(.//div[contains(@class, 'wr-c-coastandsea-summary__warnings')]/div/p[contains(@class, 'wr-c-coastandsea-summary__warnings__issued-at')])", $seaAreaElement);
                    $warnings->issuedAt = ($warningIssuedAt ?  str_replace("Issued at: ","",$warningIssuedAt) : null);
                }
                else {
                    $warnings = null;
                }
           
                $forecastItems = $xpath->query(".//div[contains(@class, 'wr-c-coastandsea-forecast__item')]", $seaAreaElement);
                $forecast = new stdClass();
                foreach ($forecastItems as $item) {
                    $label = $xpath->evaluate("string(.//h5[contains(@class, 'wr-c-coastandsea-forecast__item__label')])", $item);
                    $text = $xpath->evaluate("string(.//p[contains(@class, 'wr-c-coastandsea-forecast__item__text')])", $item);
                    $label = lcfirst(str_replace(" ", "", $label));
                    $forecast->$label = $text;
                }
                $area->warnings = $warnings;
                $area->forecast = $forecast;
                $area->id = $id;
                $seaAreas[] = $area;
                $id +=1;
            }

        }

        $data->from = $dates->from->format('Y-m-d\TH:i:s.00\Z');
        $data->to = $dates->to->format('Y-m-d\TH:i:s.00\Z');
        $data->warnings = $summaryWarnings;
        $data->summary = $summary;
        $data->seaAreas = $seaAreas;

        return $data;
    }

    public function getShippingForecast() {
        // $utils = new Utilities();
        // $jsonCache = new JsonCache();

        // $cacheData = $jsonCache->checkCache("shippingforecast");
        // if ($cacheData) {
        //     return $cacheData;
        // }

        $htmlDom = $this->getBBCpage();
        if (is_null($htmlDom)) {
            return $this->errorMessage("GSF001", "error: cannot retrieve shipping forecast");
        }

        $data = $this->makeShippingData($htmlDom);
        if (isset($data->status) && $data->status == "error") {
            return $data;
        }

        // $currentDate = new DateTime('now', new DateTimeZone('UTC')); 
        // $nextUpdate = $currentDate->modify('+4 hours');
        // $jsonCache->updateCache("shippingforecast", null, $data, $nextUpdate);

        return $data;
    }


}
