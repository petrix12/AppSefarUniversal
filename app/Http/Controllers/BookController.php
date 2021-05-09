<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RealRashid\SweetAlert\Facades\Alert;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('crud.books.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('crud.books.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validación
        $request->validate([
            'titulo' => 'required|max:255|unique:books,titulo',
            'subtitulo' => 'nullable|max:255',
            'autor' => 'nullable|max:255',
            'editorial' => 'nullable|max:255',
            'coleccion' => 'nullable|max:255',
            'enlace' => 'required|max:255|url',
            'edicion' => 'nullable|max:255',
            'paginacion' => 'nullable|max:255',
            'isbn' => 'nullable|max:255'
        ]);
       
        // Creando documento
        Book::create([
            'titulo' => trim($request->titulo),
            'subtitulo' => trim($request->subtitulo),
            'autor' => trim($request->autor),
            'editorial' => trim($request->editorial),
            'coleccion' => trim($request->coleccion),
            'fecha' => $request->fecha,
            'edicion' => trim($request->edicion),
            'paginacion' => trim($request->paginacion),
            'isbn' => trim($request->isbn),
            'notas' => trim($request->notas),
            'enlace' => trim($request->enlace),
            'claves' => $request->claves,
            'catalogador' => Auth()->user()->email
        ]); 

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha añadido el libro: ' . $request->titulo);

        // Redireccionar a la vista que invocó este método
        return redirect($request->urlPrevia);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Book  $book
     * @return \Illuminate\Http\Response
     */
    public function show(Book $book)
    {
        return view('crud.books.edit', compact('book'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Book  $book
     * @return \Illuminate\Http\Response
     */
    public function edit(Book $book)
    {
        return view('crud.books.edit', compact('book'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Book  $book
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Book $book)
    { 
        // Validación
        $request->validate([
            'titulo' => 'required|max:255|unique:books,titulo,'.$book->titulo.',titulo',
            'subtitulo' => 'nullable|max:255',
            'autor' => 'nullable|max:255',
            'editorial' => 'nullable|max:255',
            'coleccion' => 'nullable|max:255',
            'enlace' => 'required|max:255|url',
            'edicion' => 'nullable|max:255',
            'paginacion' => 'nullable|max:255',
            'isbn' => 'nullable|max:255'
        ]);

        // Actualizando documento
        $book->titulo = trim($request->titulo);
        $book->subtitulo = trim($request->subtitulo);
        $book->autor = trim($request->autor);
        $book->editorial = trim($request->editorial);
        $book->coleccion = trim($request->coleccion);
        $book->fecha = $request->fecha;
        $book->edicion = trim($request->edicion);
        $book->paginacion = trim($request->paginacion);
        $book->isbn = trim($request->isbn);
        $book->notas = trim($request->notas);
        $book->enlace = trim($request->enlace);
        $book->claves = $request->claves;
        if(is_null($book->catalogador)){
            $book->catalogador = Auth()->user()->email;
        }
       
        $book->save();

        // Mensaje 
        Alert::success('¡Éxito!', 'Se ha actualizado el libro: ' . $request->titulo);
        
        // Redireccionar a la vista que invocó este método
        return redirect($request->urlPrevia);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Book  $book
     * @return \Illuminate\Http\Response
     */
    public function destroy(Book $book)
    {
        $titulo = $book->titulo;
        
        $book->delete();

        Alert::info('¡Advertencia!', 'Se ha eliminado el libro: ' . $titulo);
        
        return back();
    }
}
