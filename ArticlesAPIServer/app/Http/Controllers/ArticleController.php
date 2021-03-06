<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Article;
use App\Picture;
use App\Category;
use App\User;

class ArticleController extends Controller {

    public function __construct() {
        
    }

    public function getArticle() {
        $data = Article::all();
        return $data->toJson();
    }

    public function getArticleWithPictures() {
        $data = Article::orderBy("created_at", "desc")->get();
        foreach ($data as $key => $value) {
            $value->categories->toArray();
            $data[$key]['pictures'] = Picture::where('article_id', $value['id'])->get();
        }
        return json_encode($data);
    }

    public function getArticleById($id) {
        $data = Article::where('id', $id)->first();
        return $data->toJson();
    }

    public function getArticleWithPicturesById($id) {
        $data = Article::where('id', $id)->first()->toArray();
        $data['pictures'] = Picture::where('article_id', $data['id'])->get();
        return json_encode($data);
    }

    public function getArticleByUserID($id) {
        $data2 = Article::where('user_record', $id)->get();
        foreach ($data2 as $article) {
            $data[$article['id']]['article'] = Article::where('id', $article['id'])->first();
            $data[$article['id']]['images'] = Picture::where('article_id', $article['id'])->get();
        }
        return json_encode($data);
    }

    public function getArticleWithPicturesByUserID($id) {
        $data = Article::where('user_record', $id)->get();
        foreach ($data as $key => $value) {
            $value->categories->toArray();
            $data[$key]['pictures'] = Picture::where('article_id', $value['id'])->get();
        }
        return json_encode($data);
    }

    public function getArticleByCategory($category) {
        $data = Category::where('name', $category)->first();
        return $data->articles->toJson();
    }

    public function getArticleWithPicturesByCategory($category) {
        $data2 = Category::where('name', $category)->first();
        $data = $data2->articles->toArray();
        foreach ($data as $key => $value) {
            $data[$key]['pictures'] = $pictures = Picture::where('article_id', $value['id'])->get();
        }
        return json_encode($data);
    }

    public function getCategory() {
        $data = Category::all();
        return $data->toJson();
    }

    public function insertArticle(Request $request) {
        // get public key
        // decrypt
        $data = $request->all();
        $payload = $this->decryptData($data);
        foreach ($payload as $key => $value) {
            $data[$key] = $payload[$key];
        }

        //insert
        $article = new Article;
        $article->title = $data['title'];
        $article->content = $data['content'];
        $article->user_record = $data['user'];
        if ($request->has('remark')) {
            $article->remark = $data['remark'];
        }
        $article->status = "In Review";
        $article->category_id = $data['category_id'];
        $article->save();

        if ($request->hasFile('images')) {
            $images = $request->file('images');
            foreach ($images as $image) {
                $picture = new Picture;
                $picture->link = $image->move('uploads/' . $data['user'] . '/' . $article->id, uniqid() . $image->getClientOriginalName());
                $picture->user_record = $data['user'];
                $picture->article_id = $article->id;
                $picture->save();
            }
        }
        $json = array('success' => 'true');
        return json_encode($json);
    }

    public function editArticle(Request $request) {
        $data = $request->all();
        $payload = $this->decryptData($data);
        foreach ($payload as $key => $value) {
            $data[$key] = $payload[$key];
        }

        $article = Article::where('id', $data['id'])->first();
        $article->title = $data['title'];
        $article->content = $data['content'];
        if ($request->has('remark')) {
            $article->remark = $data['remark'];
        }
        $article->category_id = $data['category_id'];
        $article->user_modified = $data['user'];
        $article->status = $data['status'];
        $article->save();
        $json = array('success' => 'true');
        return json_encode($json);
    }

    public function deleteArticle(Request $request) {
        $data = $request->all();
        $payload = $this->decryptData($data);
        foreach ($payload as $key => $value) {
            $data[$key] = $payload[$key];
        }
        $article = Article::where('id', $data['id'])->first();
        $article->status = "Deleted";
        $article->save();
        $json = array('success' => 'true');
        return json_encode($json);
    }

    public function insertPicture(Request $request) {
        $data = $request->all();
        $payload = $this->decryptData($data);
        foreach ($payload as $key => $value) {
            $data[$key] = $payload[$key];
        }
        if ($request->hasFile('images')) {
            $images = $request->file('images');
            foreach ($images as $image) {
                $picture = new Picture;
                $picture->link = $image->move('uploads/' . $data['user'] . '/' . $data['id'], uniqid() . $image->getClientOriginalName());
                if ($request->has('remark')) {
                    $picture->remark = $data['remark'];
                }
                $picture->user_record = $data['user'];
                $picture->article_id = $data['id'];
                $picture->save();
            }
            $json = array('success' => 'true');
            return json_encode($json);
        }
        $json = array('success' => 'false');
        return json_encode($json);
    }

    public function deletePicture(Request $request) {
        $data = $request->all();
        $payload = $this->decryptData($data);
        foreach ($payload as $key => $value) {
            $data[$key] = $payload[$key];
        }
        $picture = Picture::where('id', $data['id'])->first();
        $picture->delete();
        $json = array('success' => 'true');
        return json_encode($json);
    }

    protected function decryptData($data){
        $url = config('app.publickeyServer');
        $cSession = curl_init();
        curl_setopt($cSession, CURLOPT_URL, $url.'getKey/'.$data['user']);
        curl_setopt($cSession, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($cSession, CURLOPT_HEADER, false);
        $result_json = curl_exec($cSession);
        $json_key = json_decode((string) $result_json, true);
        curl_close($cSession);
        $pubkey = base64_decode($json_key['publicKey']);

        $encrypted = base64_decode($data['payload']);
        // openssl_private_decrypt($encrypted, $decrypted_user, $privkey);
        openssl_public_decrypt($encrypted, $decrypted, $pubkey);
        $payload = json_decode((string) $decrypted, true);
        
        return $payload;
    }

}
