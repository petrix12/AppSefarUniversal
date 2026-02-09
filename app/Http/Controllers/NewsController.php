<?php

namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NewsController extends Controller
{
    public function admin(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        $news = News::query()
            ->when($q, function ($query) use ($q) {
                $query->where('title', 'like', "%{$q}%")
                      ->orWhere('description', 'like', "%{$q}%");
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('news.panel', compact('news'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'        => ['required','string','max:255'],
            'description'  => ['required','string'],
            'header_image' => ['nullable','image','max:4096'],
        ]);

        if ($request->hasFile('header_image')) {
            $path = $request->file('header_image')->store('news', 's3');
            Storage::disk('s3')->setVisibility($path, 'public');
            $data['header_image'] = Storage::disk('s3')->url($path);
        }

        News::create($data);

        return redirect()->route('news.admin')->with('status', 'Noticia creada.');
    }

    public function update(Request $request, News $news)
    {
        $data = $request->validate([
            'title'        => ['required','string','max:255'],
            'description'  => ['required','string'],
            'header_image' => ['nullable','image','max:4096'],
        ]);

        if ($request->hasFile('header_image')) {
            $path = $request->file('header_image')->store('news', 's3');
            Storage::disk('s3')->setVisibility($path, 'public');
            $data['header_image'] = Storage::disk('s3')->url($path);
        }

        $news->update($data);

        return redirect()->route('news.admin')->with('status', 'Noticia actualizada.');
    }

    public function destroy(News $news)
    {
        $news->delete();
        return redirect()->route('news.admin')->with('status', 'Noticia eliminada.');
    }
}
