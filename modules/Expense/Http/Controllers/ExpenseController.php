<?php

namespace Modules\Expense\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Modules\Expense\Models\Expense;
use Modules\Expense\Models\ExpenseCategory;
use Modules\Expense\Http\Requests\ExpenseRequest;
use Modules\Expense\Http\Resources\ExpenseResource;
use Exception;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with(['category', 'user']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('category')) {
            $query->byCategory($request->category);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->forDateRange($request->start_date, $request->end_date);
        }

        $expenses = $query->orderBy('expense_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        // Get total for the current filtered results
        $totalAmount = $query->sum('amount');

        // Get category totals for the current filter
        $categoryTotals = Expense::select('expense_category_id')
            ->selectRaw('SUM(amount) as total')
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->search($request->search);
            })
            ->when($request->filled('start_date') && $request->filled('end_date'), function ($q) use ($request) {
                $q->forDateRange($request->start_date, $request->end_date);
            })
            ->groupBy('expense_category_id')
            ->with('category')
            ->get();

        return Inertia::render('app/expenses/expenses/Index', [
            'expenses' => ExpenseResource::collection($expenses),
            'categories' => ExpenseCategory::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->search,
                'category' => $request->category,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ],
            'total_amount' => $totalAmount,
            'category_totals' => $categoryTotals,
        ]);
    }

    public function create()
    {
        $categories = ExpenseCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('app/expenses/expenses/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(ExpenseRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $expense = Expense::create([
                ...$validated,
                'user_id' => Auth::id(),
            ]);

            DB::commit();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Expense recorded successfully',
            ]);

            return to_route('expenses.index');

        } catch (Exception $e) {
            DB::rollBack();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Failed to record expense: ' . $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function edit(Expense $expense)
    {
        $expense->load(['category', 'user']);

        $categories = ExpenseCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('app/expenses/expenses/Edit', [
            'expense' => new ExpenseResource($expense),
            'categories' => $categories,
        ]);
    }

    public function update(ExpenseRequest $request, Expense $expense)
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $expense->update($validated);

            DB::commit();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Expense updated successfully',
            ]);

            return to_route('expenses.index');

        } catch (Exception $e) {
            DB::rollBack();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Failed to update expense: ' . $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function destroy(Expense $expense)
    {
        try {
            DB::beginTransaction();

            $expense->delete();

            DB::commit();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Expense deleted successfully',
            ]);

            return to_route('expenses.index');

        } catch (Exception $e) {
            DB::rollBack();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Failed to delete expense: ' . $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    public function bulkDelete(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:expenses,id',
        ]);

        try {
            DB::beginTransaction();

            Expense::whereIn('id', $request->ids)->delete();

            DB::commit();

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Expenses deleted successfully',
            ]);

            return to_route('expenses.index');

        } catch (Exception $e) {
            DB::rollBack();

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Failed to delete expenses: ' . $e->getMessage(),
            ]);

            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}