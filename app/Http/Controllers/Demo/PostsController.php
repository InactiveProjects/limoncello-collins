<?php namespace App\Http\Controllers\Demo;

use \Validator;
use \App\Http\Requests;
use \App\Models\Post;
use \Symfony\Component\HttpFoundation\Response;
use \App\Http\Controllers\JsonApi\JsonApiController;
use \Illuminate\Contracts\Validation\ValidationException;

class PostsController extends JsonApiController
{
    private function getTime(\Closure $closure, &$time)
    {
        $time_start = microtime(true);
        try {
            return $closure();
        } finally {
            $time_end   = microtime(true);
            $time = $time_end - $time_start;
        }
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        $this->checkParametersEmpty();

        $requests = 0;
        $sqlQueries = [];
        \DB::listen(function($sql, $bindings, $time) use (&$requests, &$sqlQueries) {
            $requests++;

            if (isset($sqlQueries[$sql]) === true) {
                $sqlQueries[$sql] = $sqlQueries[$sql] + 1;
            } else {
                $sqlQueries[$sql] = 1;
            }
        });

        $builder = Post::with(['author', 'comments', 'author.posts.author', 'comments.post.comments']);

        $time = 0;
        $posts = $this->getTime(function () use ($builder) {
            return $builder->get()->all();
        }, $time);

        \Log::debug('DB query time: ' . $time . ' requests: ' . $requests);

        $response = $this->getTime(function () use ($posts) {
            return $this->getResponse($posts);
        }, $time);

        \Log::debug('Encoder time: ' . $time . ' requests: ' . $requests);
        \Log::debug('Queries: ' . json_encode($sqlQueries));

        return $response;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return Response
     */
    public function store()
    {
        $this->checkParametersEmpty();

        $content    = $this->getDocument();
        $attributes = array_get($content, 'data.attributes', []);

        $attributes['author_id'] = array_get($content, 'data.relationships.author.data.id', null);
        $attributes['site_id']   = array_get($content, 'data.relationships.site.data.id', null);

        /** @var \Illuminate\Validation\Validator $validator */
        $rules = [
            'title'     => 'required',
            'body'      => 'required',
            'author_id' => 'required|integer',
            'site_id'   => 'required|integer'
        ];
        $validator = Validator::make($attributes, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $post = new Post($attributes);
        $post->save();

        return $this->getCreatedResponse($post);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        $this->checkParametersEmpty();

        return $this->getResponse(Post::findOrFail($id));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function update($id)
    {
        $this->checkParametersEmpty();

        $content = $this->getDocument();
        $attributes = array_get($content, 'data.attributes', []);

        $attributes['author_id'] = array_get($content, 'data.relationships.author.data.id', null);
        $attributes['site_id']   = array_get($content, 'data.relationships.site.data.id', null);
        $attributes = array_filter($attributes, function ($value) {return $value !== null;});

        /** @var \Illuminate\Validation\Validator $validator */
        $rules = [
            'title'     => 'sometimes|required',
            'body'      => 'sometimes|required',
            'author_id' => 'sometimes|required|integer',
            'site_id'   => 'sometimes|required|integer'
        ];
        $validator = Validator::make($attributes, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $post = Post::findOrFail($id);
        $post->fill($attributes);
        $post->save();

        return $this->getCodeResponse(Response::HTTP_NO_CONTENT);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        $this->checkParametersEmpty();

        $comment = Post::findOrFail($id);
        $comment->delete();

        return $this->getCodeResponse(Response::HTTP_NO_CONTENT);
    }
}
