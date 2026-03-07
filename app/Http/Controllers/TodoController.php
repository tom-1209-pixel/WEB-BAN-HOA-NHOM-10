<?php

namespace App\Http\Controllers;

use App\Models\Todo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;


class TodoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()  //GET: LẤY
    {
        $todos = Todo::latest()->get();
        return $todos;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return 1;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)  //POST: TẠO
// {
//     "title" : "123",
//     "description": "456"
// }
    {   
        $request->validate([
            'title' => 'required|max:255',
            'description' => 'nullable',
        ]);
        // return $request;
        $todo = Todo::create($request->only('title', 'description'));
        return $todo;
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)//PUT: SỬA

// http://127.0.0.1:8000/api/todos/4
// {
//     "title" : "di ve",
//     "description": "di an"
// }
    {
        $request->validate(['title'=>'required|max:255']);
        $todo = Todo::findOrFail($id);
        // Select * from Todo
        // where id = $id
// {
//     "title" : "di ve",
//     "description": "di ngu"
// }

        $todo->update($request->only('title','description'));

        
        return $todo;
    }
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)//DELETE: XÓA
    {
        //
    }
}
