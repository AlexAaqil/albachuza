<?php

namespace Modules\Expense\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Expense\Models\ExpenseCategory;
use Modules\Expense\Http\Requests\ExpenseCategoryRequest;
use Modules\Expense\Http\Resources\ExpenseCategoryResource;
use Exception;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = ExpenseCategory::search($request->search)
            ->orderBy('name')
            ->with('expenses')
            ->withCount('expenses')
            ->paginate(50);

        return Inertia::render('app/expenses/categories/Index', [
            'categories' => ExpenseCategoryResource::collection($categories),
            'filters' => [
                'search' => $request->search,
            ],
        ]);
    }

    public function create()
    {
        return Inertia::render('app/expenses/categories/Create');
    }

    public function store(ExpenseCategoryRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $category = ExpenseCategory::create($validated);

            DB::commit();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Expense category created successfully',
            ]);

            return to_route('expense-categories.index');

        } catch (Exception $e) {
            DB::rollBack();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Failed to create category: ' . $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function edit(ExpenseCategory $expense_category)
    {
        return Inertia::render('app/expenses/categories/Edit', [
            'category' => $expense_category,
        ]);
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expense_category)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $expense_category->update($validated);

            DB::commit();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Expense category updated successfully',
            ]);

            return to_route('expense-categories.index');

        } catch (Exception $e) {
            DB::rollBack();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Failed to update category: ' . $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(ExpenseCategory $expense_category)
    {
        try {
            DB::beginTransaction();

            // Check if category has expenses
            if ($expense_category->expenses()->exists()) {
                throw new Exception('Cannot delete category with existing expenses. Please reassign or delete the expenses first.');
            }

            $expense_category->delete();

            DB::commit();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Expense category deleted successfully',
            ]);

            return to_route('expense-categories.index');

        } catch (Exception $e) {
            DB::rollBack();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Failed to delete category: ' . $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}