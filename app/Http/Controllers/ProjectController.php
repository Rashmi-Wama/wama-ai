<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $projects = Project::query()
            ->with('client:id,company_name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('project_name', 'like', "%{$search}%")
                        ->orWhere('project_status', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($c) => $c->where('company_name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Projects/Form', [
            'project' => null,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Project::create($this->validated($request));

        return redirect()->route('projects.index')->with('success', 'Project created successfully.');
    }

    public function edit(Project $project): Response
    {
        return Inertia::render('Projects/Form', [
            'project' => $project,
            'clients' => Client::query()->orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $project->update($this->validated($request));

        return redirect()->route('projects.index')->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'project_name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_amount' => ['required', 'numeric', 'min:0'],
            'payment_received' => ['required', 'numeric', 'min:0'],
            'project_status' => ['required', 'in:pending,in_progress,completed,on_hold,cancelled'],
        ]);
    }
}
