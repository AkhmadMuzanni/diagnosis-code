<?php

namespace App\Http\Controllers;

use GuzzleHttp;

class DiagnosisController extends Controller
{
    function index(){
        $result = $this->runBundle('https://api.staging.ehealth.id/fhir/Composition/?date=ge2021-03-01');
        // return array_count_values($result);
        $data = array(
            'diagnosisData' => array_count_values($result)
        );
        return view('main')->with($data);
    }

    function runBundle($link){
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', $link);
        $bundle = json_decode($res->getBody());

        $nextPageResult = $this->getNextPage($bundle);

        $entries = $bundle->entry;
        $diagnosis = array();
        $CODE_DIAGNOSIS = 'encounter-diagnosis';

        foreach ($entries as $entry){
            $sections = $entry->resource->section;
            foreach ($sections as $section){
                $codings = $section->code->coding;
                $isDiagnosis = false;
                foreach ($codings as $coding){
                    if($coding->code == $CODE_DIAGNOSIS){
                        $isDiagnosis = true;
                        break;
                    }
                }
                if($isDiagnosis && property_exists($section, 'entry')){
                    foreach ($section->entry as $entrySection){
                        $conditionCode = $this->getConditionCode($entrySection->reference);
                        if($conditionCode != ""){
                            array_push($diagnosis, $conditionCode);
                        }
                    }
                }
            }
        }

        return array_merge($diagnosis, $nextPageResult);
    }

    function getConditionCode($condition){
        $CODE_SYSTEM = 'https://www.icd10data.com/ICD10CM/Codes';
        $client = new GuzzleHttp\Client();
        $res = $client->request('GET', 'https://api.staging.ehealth.id/fhir/' . $condition);
        $rawConditionData = json_decode($res->getBody());
        $diagnosisCode = "";
        foreach ($rawConditionData->code->coding as $coding){
            if($coding->system == $CODE_SYSTEM){
                $diagnosisCode = $coding->code;
                break;
            }
        }

        return $diagnosisCode;
    }

    function getNextPage($bundle){
        $RELATION_NEXT = 'next';
        $nextUrl = '';
        $result = array();

        foreach ($bundle->link as $link){
            if($link->relation == $RELATION_NEXT){
                $nextUrl = $link->url;
                $result = $this->runBundle($nextUrl);
                break;
            }
        }

        return $result;
    }
}
