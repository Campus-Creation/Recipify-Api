<?php

namespace App\Http\Controllers;

use http\Env\Response;
use phpDocumentor\Reflection\Types\Array_;
use Stichoza\GoogleTranslate\GoogleTranslate;
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
        $trans = new GoogleTranslate();
	$trans->setSource('en');
	$trans->setTarget('fr');
	return $trans->translate("My Poop seems good");
    }
    public function translateCat($cat): array
    {
        $source='en';
        $target='fr';
	$trans = new GoogleTranslate();
	$trans->setSource($source);
	$trans->setTarget($target);
        $stack = array();
        for($i=0;$i<count($cat);$i++){
            $txt=ltrim($cat[$i], 'en:');
		    $txt =ltrim($txt,'fr:');
            $result = $trans->translate($txt);
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
                    if((levenshtein(strtolower($wordlist[$i]),strtolower($liste[$j]),1,3,1)<3)&&($i<$indice)){
                        $indice=$i;
                    }
                }
            }
        }
        if($indice==count($wordlist)-1){
            for($i = 0; $i < count($wordlist);$i++){
                for($j = count($liste)-1; $j >= 0; $j--){
                    foreach(explode("-",$liste[$j]) as $key=>$value){
                        if((strlen($value)>3)&&(levenshtein(strtolower($wordlist[$i]),strtolower($value),1,3,1)<3)&&($i<$indice)){
                            $indice=$i;
                        }
                    }
                }
            }
        }
        if($indice==count($wordlist)-1){
            for($i = 0; $i < count($wordlist);$i++){
                for($j = count($liste)-1; $j >= 0; $j--){
                    foreach(explode(" ",$liste[$j]) as $key=>$value){
                        if((strlen($value)>3)&&(levenshtein(strtolower($wordlist[$i]),strtolower($value),1,3,1)<3)&&($i<$indice)){
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
            $pName = "Non Renseigné";
            if(isset($json->product->product_name)) $pName = $json->product->product_name;
            $imageUrl="None";
            if(($json->product->image_url)) $imageUrl = $json->product->image_url;
            $cat = "Non Renseignées";
            $name="None";
            if(isset($json->product->categories_tags)) {
                //var_dump($json->product->product_name);
                $cat = $this->translateCat($json->product->categories_tags);
                $name = $this->realName($cat);
            }
            else{
                $name = $this->realName(explode(" ", $pName));
            }
            $nutriscore = "Non Disponible";
            if(isset($json->product->nutriscore_grade)) $nutriscore = $json->product->nutriscore_grade;
            $kcal = -1;
            if (isset($json->product->nutriments->{'energy-kcal'}))$kcal=$json->product->nutriments->{'energy-kcal'};



        }
        else return response()->json(['status'=>404]);
        return response()->json(['product_name'=>$pName,'url_image'=>$imageUrl,'category'=>$cat, 'nutriscore'=>$nutriscore, 'energy_kcal'=>$kcal, 'real_name'=>$name, 'status'=>200]);
    }
    public function getRecipe($id){
        $recette = app('db')->select("SELECT * FROM recette WHERE id = '$id'")[0];
        return response()->json(['status'=>200, 'recette'=>$recette]);
    }



    public function search($input){
        $input = urldecode($input);
        $results = app('db')->select("SELECT image, nom FROM ingredient WHERE nom = '$input'");
        if(count($results)>0){
            $nom = $results[0]->nom;
            $image = $results[0]->image;
            return response()->json(['status'=>200,'ingredient'=>['real_name'=>$nom, 'url_image'=>$image]]);
        }
        $wordlist = file("wordlist.txt");
        $LR = array();
        for($i = 0; $i < count($wordlist);$i++){
            if(((str_starts_with(str_replace("\n","",$wordlist[$i]), $input))||(levenshtein(urlencode(strtolower(str_replace("\n","",$wordlist[$i]))),urlencode(strtolower($input)),1,3,1)<3))&&(count($LR)<=15)){
                $nom=str_replace("\n","",$wordlist[$i]);
                $image = app('db')->select("SELECT image FROM ingredient WHERE nom = '$nom'")[0]->image;
                array_push($LR, ['image'=>$image,'real_name'=>str_replace("\n","",$wordlist[$i])]);
            }
        }
        return response()->json(['status'=>200,'Potential_results'=>$LR, 'input'=>urldecode($input)]);
    }
    private function stringToList($input){
        return explode(",", $input);
    }
    public function searchRecipe($products, $cat, $page){
        $cat_ref = ['Entrées'=>0,'Plats'=>1,'Desserts'=>2,'Amuses bouches'=>3, 'Sauces'=>4, 'Accompagnements'=>5, 'Boissons'=>6];
        if(str_contains($products, ",")){
            $produits = $this->stringToList($products);
            $n_prod = count($produits);
        }
        else $n_prod = 1;
        if($cat != 'all') {
            $cats = $this->stringToList($cat);
            $n_cats = count($cats);
        }
        else $n_cats = 1;
        $query = "SELECT * FROM recette WHERE (";
        if($n_prod>1){
            for($i=0; $i<$n_prod; $i++){
                if($i>0) $query.="AND ";
                $query.="noms_ingredients LIKE '%".urldecode($produits[$i])."%' ";
            }
        }
        else $query.="noms_ingredients LIKE '%".urldecode($products)."%'";
        $query.=")";
        if($n_cats>1){
            $query.=" AND (";
            for($i = 0; $i < $n_cats; $i++){
                if($i>0) $query .= "OR ";
                $query.="categorie_id = ".$cat_ref[$cats[$i]]." ";
            }
            $query.=")";
        }
        $query.=";";
        $results = app('db')->select($query);
        $return = array_slice($results, $page*10, 10);
        return response()->json(['status'=>200, 'nbr_results'=>count($results),'recettes'=>$return]);



    }

    public function bonjour($nom){
        return "Bonjour ".$nom." !\n passes une bonne journée !";
    }


    //
}
