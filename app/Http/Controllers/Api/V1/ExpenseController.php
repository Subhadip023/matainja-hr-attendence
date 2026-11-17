<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseClaimRequest;
use App\Models\AuditLog;
use App\Models\ExpenseApproval;
use App\Models\ExpenseCategory;
use App\Models\ExpenseClaim;
use App\Support\ApiResponder;
use App\Support\ReferenceCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function categories(): JsonResponse
    {
        return ApiResponder::success(ReferenceCache::get('expense_categories', fn () => ExpenseCategory::all()), 'Expense categories retrieved');
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = ExpenseClaim::with(['category', 'staff'])->latest();

        if ($user->hasRole('Manager') && ! $user->isAdmin()) {
            $teamIds = $user->staff?->subordinates()->pluck('id')->push($user->staff->id) ?? collect([-1]);
            $query->whereIn('staff_id', $teamIds);
        } elseif (! $user->isAdmin() && ! $user->hasPermissionTo('expenses.approve')) {
            $query->where('staff_id', $user->staff?->id ?? -1);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponder::success($query->paginate(20), 'Expense claims retrieved');
    }

    public function store(StoreExpenseClaimRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $receiptPath = $request->hasFile('receipt')
            ? $request->file('receipt')->store('expense-receipts', 'local')
            : null;

        $claim = ExpenseClaim::create([
            'staff_id' => $request->user()->staff->id,
            'category_id' => $validated['category_id'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'INR',
            'expense_date' => $validated['expense_date'],
            'description' => $validated['description'] ?? null,
            'receipt_path' => $receiptPath,
            'status' => $request->boolean('submit') ? 'submitted' : 'draft',
        ]);

        return ApiResponder::success($claim->load('category'), 'Expense claim created', 201);
    }

    public function show(Request $request, ExpenseClaim $expense): JsonResponse
    {
        if ($denied = $this->authorizeAccess($request, $expense)) {
            return $denied;
        }

        return ApiResponder::success(
            $expense->load(['category', 'staff', 'approvals.approver:id,name']),
            'Expense claim retrieved'
        );
    }

    public function update(Request $request, ExpenseClaim $expense): JsonResponse
    {
        if ($expense->staff_id !== $request->user()->staff?->id) {
            return ApiResponder::error('Not found.', 404);
        }

        if (! in_array($expense->status, ['draft', 'rejected'], true)) {
            return ApiResponder::error('Only draft or rejected claims can be edited.', 409);
        }

        $validated = $request->validate([
            'category_id' => ['sometimes', 'exists:expense_categories,id'],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:99999999'],
            'expense_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $expense->update($validated);

        return ApiResponder::success($expense->fresh()->load('category'), 'Expense claim updated');
    }

    public function submit(Request $request, ExpenseClaim $expense): JsonResponse
    {
        if ($expense->staff_id !== $request->user()->staff?->id) {
            return ApiResponder::error('Not found.', 404);
        }

        if (! in_array($expense->status, ['draft', 'rejected'], true)) {
            return ApiResponder::error('This claim cannot be submitted.', 409);
        }

        $expense->update(['status' => 'submitted']);

        return ApiResponder::success($expense, 'Expense claim submitted');
    }

    public function approve(Request $request, ExpenseClaim $expense): JsonResponse
    {
        return $this->decide($request, $expense, 'approved');
    }

    public function reject(Request $request, ExpenseClaim $expense): JsonResponse
    {
        $request->validate(['comments' => ['required', 'string', 'max:1000']]);

        return $this->decide($request, $expense, 'rejected');
    }

    public function markPaid(Request $request, ExpenseClaim $expense): JsonResponse
    {
        $validated = $request->validate([
            'payment_reference' => ['nullable', 'string', 'max:255'],
        ]);

        if ($expense->status !== 'approved') {
            return ApiResponder::error('Only approved claims can be marked paid.', 409);
        }

        $expense->update([
            'status' => 'paid',
            'paid_at' => now()->toDateString(),
            'payment_reference' => $validated['payment_reference'] ?? null,
        ]);

        AuditLog::record('expense.marked_paid', $expense);

        return ApiResponder::success($expense, 'Expense marked as paid');
    }

    private function decide(Request $request, ExpenseClaim $expense, string $decision): JsonResponse
    {
        if ($expense->status !== 'submitted') {
            return ApiResponder::error('Only submitted claims can be decided.', 409);
        }

        ExpenseApproval::create([
            'expense_claim_id' => $expense->id,
            'approver_id' => $request->user()->id,
            'decision' => $decision,
            'comments' => $request->input('comments'),
        ]);

        $expense->update(['status' => $decision]);

        AuditLog::record("expense.{$decision}", $expense);

        return ApiResponder::success($expense->fresh()->load('category'), "Expense claim {$decision}");
    }

    private function authorizeAccess(Request $request, ExpenseClaim $expense): ?JsonResponse
    {
        $user = $request->user();

        if ($user->isAdmin()
            || $user->hasPermissionTo('expenses.approve')
            || $expense->staff_id === $user->staff?->id
            || $expense->staff?->manager_id === $user->staff?->id) {
            return null;
        }

        return ApiResponder::error('Not found.', 404);
    }
}
