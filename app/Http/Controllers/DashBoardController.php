<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Log;
use App\Models\GeneralParam;
use App\Models\Octopus;
use App\Models\Zoho;
use App\Models\Dolibarr;
use App\Models\Relation;

class DashBoardController extends Controller
{

    public $octopusController;
    public $databaseController;
    public $zohoController;
    public $dolibarrController;

    public function index(Request $request) {

        if($request->code){
            $this->zohoController = new ZohoController();
            $this->zohoController->zoho = Zoho::get()->first();
            $tokens = $this->zohoController->getToken($request->code);
            $this->zohoController->zoho->accessToken = $tokens["access_token"];
            $this->zohoController->zoho->save();
            $generalParams = GeneralParam::get()->first();
            $generalParams->lastZohoAuth = now();
            $generalParams->save();
        }

        $this->octopusController = new OctopusController();
        $this->octopusController->octopus = Octopus::where("action", "receive")->get()->first();

        $journalKeys = $this->octopusController->getJournalKeys();

        $generalParams = GeneralParam::get()->first();
        $lastZohoAuth = time() - strtotime($generalParams->lastZohoAuth) < 3601;

        return View::make('dashboard.index', 
            [
             "modifiedBookings" => null,
             "lastUpdated" => $generalParams->lastUpdated,
             "lastZohoAuth" => $lastZohoAuth,
             "journals" => $journalKeys
            ]
        );
    }

    public function resetDatabase() {
        $this->databaseController = new DatabaseController();
        $this->databaseController->resetDatabase();

        $this->octopusController = new OctopusController();
        $this->octopusController->octopus = Octopus::where("action", "receive")->get()->first();

        $journalKeys = $this->octopusController->getJournalKeys();

        $generalParams = GeneralParam::get()->first();
        $lastZohoAuth = time() - strtotime($generalParams->lastZohoAuth) < 3601;

        return View::make('dashboard.index', 
            [
             "modifiedBookings" => null,
             "lastUpdated" => $generalParams->lastUpdated,
             "lastZohoAuth" => $lastZohoAuth,
             "journals" => $journalKeys,
             "reset" => "Réinitialisation exécutée"
            ]
        );
    }

    public function updateDB(Request $request) {
        $completeBookings = [];

        //Récupère les paramètres du formulaire
        $journalKeys = $request->get("journals");
        $timestamp = $request->timestamp . " 00:00:00.000";

        $this->octopusController = new OctopusController();
        $this->octopusController->octopus = Octopus::where("action", "receive")->get()->first();
        $this->octopusController->token = $this->octopusController->getToken();
        $this->octopusController->dossierToken = $this->octopusController->getDossierToken();

        $this->databaseController = new DatabaseController();
        $params = GeneralParam::get()->first();

        //Pour chaque clé de journal, appelle l'api octopus pour récupérer les bookings correspondant et mets à jour la base de donnée
        if ($journalKeys) {
            foreach ($journalKeys as $journalKey) {
                //Retire les espaces
                $journalKey = trim($journalKey);
                
                $bookings = $this->octopusController->getBookings($journalKey, $timestamp);
                if(!isset($bookings["technicalInfo"])){
                    $this->databaseController->fillDB($bookings, $this->octopusController);
                }
                //Juste pour le débuggage
                array_push($completeBookings, $bookings);
            }

            $params->lastUpdated = date('Y-m-d H:i:s');
            $params->save();
        }

        $journalKeys = $this->octopusController->getJournalKeys();
        $lastZohoAuth = time() - strtotime($params->lastZohoAuth) < 3601;

        return View::make('dashboard.index', 
            [
             "modifiedBookings" => $completeBookings,
             "lastUpdated" => $params->lastUpdated,
             "lastZohoAuth" => $lastZohoAuth,
             "journals" => $journalKeys
            ]
        );
    }

    public function refreshZohoToken() {

        $this->octopusController = new OctopusController();
        $this->octopusController->octopus = Octopus::where("action", "receive")->get()->first();
        $journalKeys = $this->octopusController->getJournalKeys();

        $this->zohoController = new ZohoController();
        $this->zohoController->zoho = Zoho::get()->first();
        $this->zohoController->zoho->accessToken = $this->zohoController->refreshToken();

        $generalParams = GeneralParam::get()->first();
        $lastZohoAuth = time() - strtotime($generalParams->lastZohoAuth) < 3601;


        return View::make('dashboard.index', 
            [
             "zohoToken" => $this->zohoController->zoho->accessToken,
             "modifiedBookings" => null,
             "lastUpdated" => $generalParams->lastUpdated,
             "lastZohoAuth" => $lastZohoAuth,
             "journals" => $journalKeys
            ]
        );
    }
    
    public function sendDataZoho() {

        $this->octopusController = new OctopusController();
        $this->octopusController->octopus = Octopus::where("action", "receive")->get()->first();
        $journalKeys = $this->octopusController->getJournalKeys();

        $this->zohoController = new ZohoController();
        $this->zohoController->zoho = Zoho::get()->first();

        $relations = \App\Models\Relation::all();
        $zohoRelations = $this->zohoController->getRelations();
        foreach ($relations as $relation) {
            $hasRelation = $this->zohoController->hasRelation($zohoRelations, trim($relation->name));
            if(!$hasRelation){
                $this->zohoController->createRelation($relation)["data"]["ID"];
            }
        }

        $bookingController = new BookingController();
        $bookings = \App\Models\Booking::with("relation")->get();
        $bookingLines = \App\Models\BookingLine::all();
        $bookings = $bookingController->calculateTVA($bookings, $bookingLines);
        $zohoBookingLog = [];
        $zohoBookings = $this->zohoController->getBookings();
        $zohoRelations = $this->zohoController->getRelations();
        foreach ($bookings as $booking) {
            $hasRelation = $this->zohoController->hasRelation($zohoRelations, trim($booking->relation->name));
            $relationId = $hasRelation['ID'];

            $hasBooking = $this->zohoController->hasBooking($zohoBookings, $booking->alphaNumericalNumber);
            if(!$hasBooking){
                $result = $this->zohoController->createBooking($booking, $relationId);
                if(isset($result["error"])){
                    $zohoBookingLog[$booking->alphaNumericalNumber] = $result["error"];
                }
                else{
                    $zohoBookingLog[$booking->alphaNumericalNumber] = "Ajouté";
                }
                
            }
            else{
                $result = $this->zohoController->updateBooking($hasBooking['ID'], $booking, $relationId);
                if(isset($result["error"])){
                    $zohoBookingLog[$booking->alphaNumericalNumber] = $result["error"];
                }
                else{
                    $zohoBookingLog[$booking->alphaNumericalNumber] = "Mise à jour";
                }
            }
            foreach ($bookingLines as $line) {
                if($line->booking_id == $booking->id){
                    $line->delete();
                }
            }
            $booking->delete();
        }

        $generalParams = GeneralParam::get()->first();
        $lastZohoAuth = time() - strtotime($generalParams->lastZohoAuth) < 3601;

        return View::make('dashboard.index', 
            [
             "modifiedBookings" => null,
             "lastUpdated" => $generalParams->lastUpdated,
             "lastZohoAuth" => $lastZohoAuth,
             "journals" => $journalKeys,
             "zohoBookingLog" => $zohoBookingLog
            ]
        );
    }

    public function transferDoliOcto(Request $request) {

        $this->octopusController = new OctopusController();
        $this->octopusController->octopus = Octopus::where("action", "send")->get()->first();
        $this->octopusController->token = $this->octopusController->getToken();
        $this->octopusController->dossierToken = $this->octopusController->getDossierToken();
        $validBookings = [];
        $bookings = [];
        $octoBookingLog = [];

        $this->dolibarrController = new DolibarrController();
        $this->dolibarrController->dolibarr = Dolibarr::get()->first();
        $dolibarrBookings = $this->dolibarrController->getBookings($request->timestamp);

        if(isset($dolibarrBookings["error"])) {
            $octoBookingLog["Code"] = $dolibarrBookings["error"]["code"];
            $octoBookingLog["Message"] = $dolibarrBookings["error"]["message"];
        }
        else {
            foreach ($dolibarrBookings as $dolibarrBooking) {
                if($dolibarrBooking["brouillon"] == null){
                    array_push($validBookings, $dolibarrBooking);
                }
            }
            $octopusBookings = $this->octopusController->getBookings("V1", "1980-01-01 00:00:00.000");
            foreach ($validBookings as $key => $validBooking) {
                $bool = true;
                foreach ($octopusBookings as $key => $octopusBooking) {
                    if(isset($octopusBooking["reference"])){
                        if($octopusBooking["reference"] == $validBooking["ref"]){
                            $bool = false;
                        }
                    }
                }
                if($bool){
                    array_push($bookings, $validBooking);
                }
                
            }
    
            foreach ($bookings as $booking) {
                $relation = $this->dolibarrController->getRelationById($booking["socid"]);
                if(isset($this->octopusController->getRelationByName($relation["name"])["errorCode"])){
                    $this->octopusController->createRelation($relation);
                }
                $relationId = $this->octopusController->getRelationByName($relation["name"])[0]["relationIdentificationServiceData"]["relationKey"]["id"];
                $externalRealtionId = $this->octopusController->getRelationByName($relation["name"])[0]["relationIdentificationServiceData"]["externalRelationId"];
    
                $result = $this->octopusController->createBooking($booking, $relationId, $externalRealtionId);
                if(isset($result["errorCode"])) {
                    $octoBookingLog[$booking["ref"]] = "Erreur ".$result["errorCode"]." : ".$result["technicalInfo"];
                }
                else {
                    $octoBookingLog[$booking["ref"]] = "Ajouté";
                }
            }
        }
        
        $this->octopusController->octopus = Octopus::where("action", "receive")->get()->first();
        $this->octopusController->token = $this->octopusController->getToken();
        $this->octopusController->dossierToken = $this->octopusController->getDossierToken();
        $journalKeys = $this->octopusController->getJournalKeys();

        $generalParams = GeneralParam::get()->first();
        $lastZohoAuth = time() - strtotime($generalParams->lastZohoAuth) < 3601;
        

        return View::make('dashboard.index', 
            [
             "modifiedBookings" => null,
             "lastUpdated" => $generalParams->lastUpdated,
             "lastZohoAuth" => $lastZohoAuth,
             "journals" => $journalKeys,
             "octoBookingLog" => $octoBookingLog
            ]
        );
    }
}
