<?php

namespace App\Http\Controllers;

use DonatelloZa\RakePlus\RakePlus;
use Statickidz\GoogleTranslate;
use function MongoDB\BSON\toJSON;

class MyController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }
    public function test()
    {
        return "caca";
    }
    public function translateCat($cat): array
    {
        $source='en';
        $target='fr';
        $trans = new GoogleTranslate();
        $stack = array();
        for($i=0;$i<count($cat);$i++){
            $txt=ltrim($cat[$i], 'en:');
            $result = $trans->translate($source, $target, $txt);
            array_push($stack,$result);
        }
        return $stack;
    }

    public function realName($liste): string
    {
        $wordlist = file("wordlist.txt");
        $indice=count($wordlist)-1;
        for($i = 0; $i < count($wordlist);$i++){
            for($j = 0; $j < count($liste); $j++){
                if((strcasecmp(str_replace("\n","",$wordlist[$i]),$liste[$j])==0)&&($i<$indice)){
                    $indice=$i;
                }
            }
        }

        if($indice==count($wordlist)-1){
            for($i = 0; $i < count($wordlist);$i++){
                for($j = count($liste)-1; $j >= 0; $j--){
                    if((levenshtein(strtolower($wordlist[$i]),strtolower($liste[$j]),1,3,1)<4)&&($i<$indice)){
                        $indice=$i;
                    }
                }
            }
        }
        if($indice==count($wordlist)-1){
            for($i = 0; $i < count($wordlist);$i++){
                for($j = count($liste)-1; $j >= 0; $j--){
                    foreach(explode(" ",$liste[$j]) as $key=>$value){
                        if((strlen($value)>3)&&(levenshtein(strtolower($wordlist[$i]),strtolower($value),1,3,1)<5)&&($i<$indice)){
                            $indice=$i;
                        }
                    }
                }
            }
        }
        return str_replace("\n", "", $wordlist[$indice]);
    }

    public function getProduct($barCode): \Illuminate\Http\JsonResponse
    {
        $json = json_decode(file_get_contents('https://world-fr.openfoodfacts.org/api/v0/product/'.$barCode.'json'));
        if($json->status_verbose == "product found") {
            $pName = $json->product->product_name;
            $imageUrl = $json->product->image_url;
            $cat = $this->translateCat($json->product->categories_tags);
            $nutriscore = "Non Disponible";
            if(isset($json->product->nutriscore_grade)) $nutriscore = $json->product->nutriscore_grade;
            $kcal = "Non Disponible";
            if (isset($json->product->nutriments->{'energy-kcal'}))$kcal=$json->product->nutriments->{'energy-kcal'};
            $name = $this->realName($cat);

        }
        else return response()->json(['Status'=>'Produit Non Trouvé']);
        return response()->json(['Nom du produit'=>$pName,'Url_Image'=>$imageUrl,'Categorie'=>$cat, 'nutriscore'=>$nutriscore, 'energy-kcal'=>$kcal, 'VraiNom'=>$name]);
    }
    public function getRecipe($listeenstring){
        return "caca en construction";
    }
    private function stringToList($liste){ //

    }
    public function test2(){

        $text = "The most Important thing i have learn this year is chess. chess changed my life !";
        $rake = RakePlus::create($text, 'en_US');
        $phrase_scores = $rake->sortByScore('desc')->scores();
//        return "pipi";
        print_r($phrase_scores);
    }
    //
}
