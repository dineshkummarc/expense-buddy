@extends('layouts.app')

@section('title', 'Categories')
@section('heading', 'Transaction Categories')
@section('subheading', 'Income and expense labels')

@section('actions')
    <x-form-modal-trigger
        :config="[
            'title' => 'Create Category',
            'action' => route('categories.store'),
            'method' => 'POST',
            'fields' => ['name' => '', 'type' => 'expense', 'status' => 'active'],
        ]"
        class="btn-primary whitespace-nowrap"
    >
        <x-ming-icon name="system.add" class="h-4 w-4" />
        Add Category
    </x-form-modal-trigger>
@endsection

@section('content')
    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card label="Total Categories" :value="(string) $stats['total']" color="brand" />
        <x-stat-card label="Active" :value="(string) $stats['active']" color="emerald" />
        <x-stat-card label="Archived" :value="(string) $stats['archived']" color="rose" />
    </div>

    <x-panel class="mt-6" title="Category List" subtitle="Archive instead of delete to preserve history">
        <form method="GET" class="mb-5 grid gap-3 sm:grid-cols-4">
            <select name="type" class="input">
                <option value="">All types</option>
                <option value="income" @selected(($filters['type'] ?? '') === 'income')>Income</option>
                <option value="expense" @selected(($filters['type'] ?? '') === 'expense')>Expense</option>
            </select>
            <select name="status" class="input">
                <option value="">All statuses</option>
                <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Archived</option>
            </select>
            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="btn-primary">Filter</button>
                <a href="{{ route('categories.index') }}" class="btn-secondary">Reset</a>
            </div>
        </form>

        <div class="overflow-x-auto -mx-5 px-5">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800">
                        <th class="th">Name</th>
                        <th class="th">Type</th>
                        <th class="th">Status</th>
                        <th class="th text-right">Transactions</th>
                        <th class="th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse ($categories as $category)
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/30 {{ $category->status === 'inactive' ? 'opacity-70' : '' }}">
                            <td class="td font-medium">
                                {{ $category->name }}
                                @if ($category->is_system)
                                    <span class="text-[10px] badge bg-slate-100/60 text-slate-600 border border-slate-200/60 dark:bg-slate-800/40 dark:text-slate-400 dark:border-slate-700/40 select-none ml-1.5">
                                        System
                                    </span>
                                @endif
                            </td>
                            <td class="td">
                                <x-transaction-type-badge :type="$category->type === 'income' ? 'income' : 'expense'" />
                            </td>
                            <td class="td">
                                <span class="badge {{ $category->status === 'active' ? 'badge-income' : 'badge-expense' }}">
                                    {{ $category->status === 'active' ? 'Active' : 'Archived' }}
                                </span>
                            </td>
                            <td class="td text-right font-mono text-sm">{{ $category->transactions_count }}</td>
                            <td class="td">
                                <div class="flex justify-end gap-2">
                                    @if ($category->is_system)
                                        <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs opacity-40 cursor-not-allowed" disabled>
                                            Edit
                                        </button>
                                        @if ($category->status === 'active')
                                            <span class="text-xs font-medium text-slate-400 dark:text-slate-600 py-1.5 select-none">
                                                Archive
                                            </span>
                                        @else
                                            <button type="button" class="btn-secondary !px-3 !py-1.5 text-xs opacity-40 cursor-not-allowed" disabled>
                                                Restore
                                            </button>
                                        @endif
                                    @else
                                        @if ($category->status === 'active')
                                            <x-form-modal-trigger
                                                :config="[
                                                    'title' => 'Edit Category',
                                                    'action' => route('categories.update', $category->id),
                                                    'method' => 'PUT',
                                                    'fields' => [
                                                        'name' => $category->name,
                                                        'type' => $category->type,
                                                        'status' => $category->status,
                                                    ],
                                                ]"
                                                class="btn-secondary !px-3 !py-1.5 text-xs"
                                            >
                                                Edit
                                            </x-form-modal-trigger>
                                            <button type="button"
                                                    data-confirm="Archive &quot;{{ $category->name }}&quot;? Existing transactions will keep this category, but it will be hidden from new entries."
                                                    form="archive-category-{{ $category->id }}"
                                                    class="text-xs font-medium text-amber-600 hover:underline">
                                                Archive
                                            </button>
                                        @else
                                            <button type="button"
                                                    data-confirm="Restore &quot;{{ $category->name }}&quot; for use in new transactions?"
                                                    form="restore-category-{{ $category->id }}"
                                                    class="btn-secondary !px-3 !py-1.5 text-xs">
                                                Restore
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                No categories match your filters.
                                <x-form-modal-trigger
                                    :config="[
                                        'title' => 'Create Category',
                                        'action' => route('categories.store'),
                                        'method' => 'POST',
                                        'fields' => ['name' => '', 'type' => 'expense', 'status' => 'active'],
                                    ]"
                                    class="ml-1 font-medium text-brand-600 hover:underline"
                                >
                                    Create one
                                </x-form-modal-trigger>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-panel>

    @foreach ($categories as $category)
        @if ($category->status === 'active')
            <form id="archive-category-{{ $category->id }}" method="POST" action="{{ route('categories.archive', $category->id) }}" class="hidden">
                @csrf
            </form>
        @else
            <form id="restore-category-{{ $category->id }}" method="POST" action="{{ route('categories.restore', $category->id) }}" class="hidden">
                @csrf
            </form>
        @endif
    @endforeach

    <x-form-modal>
        <form id="form-modal-form" method="POST" action="{{ route('categories.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="status" value="active" data-modal-field="status">

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Name</label>
                <input type="text" name="name" data-modal-field="name" class="input" placeholder="Category name" required>
            </div>

            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Type</label>
                <select name="type" data-modal-field="type" class="input" required>
                    <option value="income">Income</option>
                    <option value="expense">Expense</option>
                </select>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="btn-secondary" data-form-modal-dismiss>Cancel</button>
                <button type="submit" class="btn-primary">Save Category</button>
            </div>
        </form>
    </x-form-modal>
@endsection
