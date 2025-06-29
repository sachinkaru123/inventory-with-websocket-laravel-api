<?php

namespace App\Http\Controllers;

use App\Events\ItemCountExeed;
use App\Models\Item;
use Illuminate\Http\Request;
use App\Events\ItemUpdated;

class ItemController extends Controller
{
    public function index()
    {
        return Item::all();
    }

    public function show(Item $item)
    {
        return response()->json($item);
    }

    public function store(Request $request)
    {
        $item = Item::create($request->validate([
            'name' => 'required|string',
            'stock' => 'required|integer',
        ]));
        broadcast(new ItemUpdated($item))->toOthers();
        return response()->json($item, 201);
    }

    public function update(Request $request, Item $item)
    {
        $item->update($request->validate([
            'name' => 'sometimes|required|string',
            'stock' => 'sometimes|required|integer',
        ]));
        broadcast(new ItemUpdated($item))->toOthers();
        return response()->json($item);
    }

    public function destroy(Item $item)
    {
        $item->delete();
        return response()->noContent();
    }
}
