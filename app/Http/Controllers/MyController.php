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
        $this->middleware('auth', ['only'=>[
            'getProduct',
            'getRecipe',
            'search',
            'searchRecipe',
            'bonjour',
            'test'
        ]]);
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
        if(str_replace("\n", "", $wordlist[$indice]) == 'zuccata') return '';
        return str_replace("\n", "", $wordlist[$indice]);
    }
    private function realPname($pname){
        $LR = array();
        $wordlist = file("wordlist.txt");
        $mots = explode(" ", $pname);
        $nmots = count($mots);
        $d=3;
        if($nmots==1){
            for($k = 0; $k<count($wordlist); $k++){
                if(levenshtein(str_replace("\n","",strtolower($wordlist[$k])),strtolower($pname),1,3,1)<3){
                    array_push($LR, str_replace("\n","",strtolower($wordlist[$k])));
                }
            }
        }
        else {
            for ($i = 0; $i < $nmots; $i++) {
                for ($j = 0; $j < $nmots - $i+1; $j++) {
                    $a = implode(" ", array_slice($mots, $i, $j));
                    for ($k = 0; $k < count($wordlist); $k++) {
                        if (levenshtein(str_replace("\n", "", strtolower($wordlist[$k])), strtolower($a), 1, 3, 1) < $d) {
                            $d = levenshtein(str_replace("\n", "", strtolower($wordlist[$k])), strtolower($a), 1, 3, 1);
                            array_push($LR, str_replace("\n", "", strtolower($wordlist[$k])));
                        }
                    }
                }
            }
        }
        if(count($LR)==0) return null;
        if(count($LR)==1) return $LR[0];
        $l=0;
        $r='caca';
        for($i=0; $i<count($LR);$i++){
            if(strlen($LR[$i])>$l){
                $l=strlen($LR[$i]);
                $r=$LR[$i];
            }
        }
        return $r;

    }
    private function exceptions($pName){
        $mots = explode(" ", $pName);
        for($i = 0; $i < count($mots); $i++){
            if($mots[$i] == "Parmigiano") return "parmesan";
        }
        return null;
    }
    public function getProduct($barCode): \Illuminate\Http\JsonResponse
    {
        $json = json_decode(file_get_contents('https://world-fr.openfoodfacts.org/api/v0/product/'.$barCode.'json'));
        if($json->status_verbose == "product found") {
            $pName = "null";
            if(isset($json->product->product_name)) $pName = $json->product->product_name;
            $imageUrl="None";
            if(($json->product->image_url)) $imageUrl = $json->product->image_url;
            $cat = "null";
            $name="None";
            if(isset($json->product->categories_tags)) {
                //var_dump($json->product->product_name);
                $cat = $this->translateCat($json->product->categories_tags);

            }
            $n = $this->exceptions($pName);
            if(isset($n)){
                $name = $n;
            }
            else {
                $n = $this->realPname($pName);
                if(isset($json->product->categories_tags)) {
                    $ncat = $this->realName($cat);
                    $lmax = max(strlen($n),strlen($ncat));
                    if($lmax==strlen($n)) $name = $n;
                    else $name = $ncat;
                    }
                else{
                    $name = $n;
                }

//            else{
//                $name = $this->realName(explode(" ", $pName));
//            }
            }
//            $name = $this->realPname($pName);
            $nutriscore = "null";
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
        $input2 = "\"".$input."\"";
//        $results = app('db')->select("SELECT image, nom FROM ingredient WHERE nom = $input2");
//        if(count($results)>0){
//            $nom = $results[0]->nom;
//            $image = $results[0]->image;
//            return response()->json(['status'=>200,'ingredient'=>['real_name'=>ucfirst($nom), 'url_image'=>$image]]);
//        }
//        $input = array($input);
        $wordlist = file("wordlist.txt");
        $LR = array();
        for($i = 0; $i < count($wordlist);$i++){
            if(((str_starts_with(str_replace("\n","",$wordlist[$i]), $input))||(levenshtein(urlencode(strtolower(str_replace("\n","",$wordlist[$i]))),urlencode(strtolower($input)),1,3,1)<3))&&(count($LR)<=15)){
                $nom=str_replace("\n","",$wordlist[$i]);
                $nom = "\"".$nom."\"";
                $image = app('db')->select("SELECT image FROM ingredient WHERE nom = $nom")[0]->image;
                array_push($LR, ['url_image'=>$image,'real_name'=>ucfirst(str_replace("\n","",$wordlist[$i]))]);
            }
        }
        return response()->json(['status'=>200,'Potential_results'=>$LR]);
    }
    private function stringToList($input){
        return explode(",", $input);
    }
    private function getUrlImage($name){
        if (isset(json_decode($this->search($name))->ingredient)) return json_decode($this->search($name))->ingredient->url_image;
        else{
            $wordlist = file("wordlist.txt");
            for($i = 0; $i < count($wordlist);$i++){
                if(strpos($name, str_replace("\n","",$wordlist[$i])) != false){
                    $nom=str_replace("\n","",$wordlist[$i]);
                    $nom = "\"".$nom."\"";
                    $image = app('db')->select("SELECT image FROM ingredient WHERE nom = $nom")[0]->image;
                    return $image;
                }
            }
        }
        return '0';
    }

    private function formatRecipe($recettes){
        $return = array();
        $n = count($recettes);
        $ingredients = array();
        $etapes=array();
        for($i=0;$i<$n;$i++){
            $return[$i] = new \stdClass();
            $return[$i]->nom = $recettes[$i]->nom;
            $return[$i]->temps_total = $recettes[$i]->temps_total;
            $return[$i]->quantite = $recettes[$i]->quantite;
            $return[$i]->type_quantite = $recettes[$i]->type_quantite;
            $return[$i]->prix = $recettes[$i]->prix;
            $return[$i]->categorie_id = $recettes[$i]->categorie_id;
            $etapes = explode("~", $recettes[$i]->etapes);
            for($j=0;$j<count($etapes); $j++){
                $return[$i]->etapes[$j] = $etapes[$j];
            }
            $return[$i]->id = $recettes[$i]->id;
            $return[$i]->url_image = $recettes[$i]->image;
            $ingredients = $this->stringToList($recettes[$i]->noms_ingredients);
            $quantites = $this->stringToList($recettes[$i]->quantites_ingredients);

            for($j=0;$j<count($ingredients)-1;$j++){
                $return[$i]->ingredients[$j] = ['name'=>$ingredients[$j], 'Qt'=>$quantites[$j], 'url_image'=>$this->getUrlImage($ingredients[$j])];
            }
        }
        return $return;
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
                if(strtolower(urldecode($produits[$i]))=='pomme'){
                    $query.="(noms_ingredients LIKE '%pomme,%' OR noms_ingredients LIKE '%pommes,%')";
                    continue;
                }
                else {
                    $query .= "noms_ingredients LIKE '%" . urldecode($produits[$i]) . "%' ";
                }
            }
        }
        else
        {
            if(strtolower(urldecode($products))=='pomme'){
                $query.="(noms_ingredients LIKE '%pomme,%' OR noms_ingredients LIKE '%pommes,%')";
            }
            else {
                $query .= "noms_ingredients LIKE '%" . urldecode($products) . "%'";
            }
        }
        $query.=")";
        if($n_cats>1){
            $query.=" AND (";
            for($i = 0; $i < $n_cats; $i++){
                if($i>0) $query .= "OR ";
                $query.="categorie_id = ".$cat_ref[$cats[$i]]." ";
            }
            $query.=")";
        }
        $offset = $page*10;
        $query.= " LIMIT 10 OFFSET $offset ;";
        $results = app('db')->select($query);
        $results = $this->formatRecipe($results);

        return response()->json(['status'=>200,'recettes'=>$results]);
    }


    public function bonjour($nom){
        return "Bonjour ".$nom." !\n passes une bonne journée !";
    }


    //
}
