<?php

namespace NamespacePlaceholder;

use RootNamespacePlaceholder\Models\ModelNamePlaceholder;
use Brickhouse\Http\Controller;
use Brickhouse\Http\Response;

final class ClassNamePlaceholder extends Controller
{
    /**
     * Renders a list of all items of the given resource type.
     *
     * @return Response
     */
    public function index(): Response
    {
        return render('ModelNamePlaceholderLowercase/index', [
            'items' => ModelNamePlaceholder::all()
        ]);
    }

    /**
     * Receives parameters to create one new item and save it to the database.
     *
     * @return Response
     */
    public function create(): Response
    {
        return render('ModelNamePlaceholderLowercase/create', [
            'item' => ModelNamePlaceholder::create($this->request->all())
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
        return render('ModelNamePlaceholderLowercase/show', [
            'item' => ModelNamePlaceholder::find($id)
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
        return render('ModelNamePlaceholderLowercase/update', [
            'item' => ModelNamePlaceholder::find($id)?->save($this->request->all())
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
        return render('ModelNamePlaceholderLowercase/destroy', [
            'item' => ModelNamePlaceholder::find($id)?->delete()
        ]);
    }
}
