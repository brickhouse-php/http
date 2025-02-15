<?php

namespace {{controllerNamespace}};

use App\Models\{{modelClass}};
use Brickhouse\Http\Controller;
use Brickhouse\Http\Response;

final class {{controllerClass}} extends Controller
{
    /**
     * Renders a list of all items of the given resource type.
     *
     * @return Response
     */
    public function index(): Response
    {
        return render('{{#lower modelClass}}/index', [
            'items' => {{modelClass}}::all()
        ]);
    }

    /**
     * Renders a form for creating a new item.
     *
     * @return Response
     */
    public function new(): Response
    {
        return render('{{#lower modelClass}}/new', [
            'item' => {{modelClass}}::new()
        ]);
    }

    /**
     * Receives parameters to create one new item and save it to the database.
     *
     * @return Response
     */
    public function create(): Response
    {
        return render('{{#lower modelClass}}/create', [
            'item' => {{modelClass}}::create($this->request->all())
        ]);
    }

    /**
     * Renders an individual item by it's ID.
     *
     * @param string    $id
     *
     * @return Response
     */
    public function show(string $id): Response
    {
        return render('{{#lower modelClass}}/show', [
            'item' => {{modelClass}}::find($id)
        ]);
    }

    /**
     * Renders a form for updating an existing item.
     *
     * @return Response
     */
    public function edit(string $id): Response
    {
        return render('{{#lower modelClass}}/edit', [
            'item' => {{modelClass}}::find($id)
        ]);
    }

    /**
     * Receives parameters to update an existing item in the database and save it.
     *
     * @param string    $id
     *
     * @return Response
     */
    public function update(string $id): Response
    {
        return render('{{#lower modelClass}}/update', [
            'item' => {{modelClass}}::find($id)?->save($this->request->all())
        ]);
    }

    /**
     * Receives an ID for an item to be deleted from the database.
     *
     * @param string    $id
     *
     * @return Response
     */
    public function destroy(string $id): Response
    {
        return render('{{#lower modelClass}}/destroy', [
            'item' => {{modelClass}}::find($id)?->delete()
        ]);
    }
}
