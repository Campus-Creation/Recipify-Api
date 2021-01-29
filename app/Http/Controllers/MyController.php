<?php

namespace App\Http\Controllers;

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
    public function getProduct($barCode){
        $json = json_decode(file_get_contents('https://world.openfoodfacts.org/api/v0/product/'.$barCode.'json'));
        if($json->status_verbose == "product found") {
            $pName = $json->product->product_name;
            $imageUrl = $json->product->image_url;
            $cat = $json->product->categories_tags;
        }
        else return response()->json(['Status'=>'Produit Non Trouvé']);
        return response()->json(['Nom du produit'=>$pName,'Url_Image'=>$imageUrl,'Categorie'=>$cat]);
    }
    //
}
