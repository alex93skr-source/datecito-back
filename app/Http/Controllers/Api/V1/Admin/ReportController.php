<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateReportRequest;
use App\Http\Resources\Admin\ReportResource;
use App\Models\Report;
use App\Services\Admin\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Cola de reportes de la comunidad.
 */
class ReportController extends Controller
{
    public function __construct(private AuditLog $audit) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'status' => ['nullable', Rule::in([...array_column(ReportStatus::cases(), 'value'), 'all'])],
            'reason' => ['nullable', 'string', 'max:60'],
            'reported_id' => ['nullable', 'string', 'ulid'],
            'reporter_id' => ['nullable', 'string', 'ulid'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(['oldest', 'newest'])],
        ]);

        $reports = Report::query()
            ->when(
                $request->filled('status'),
                fn (Builder $q) => $request->input('status') === 'all' ? $q : $q->where('status', $request->string('status')),
                fn (Builder $q) => $q->whereIn('status', [ReportStatus::Open, ReportStatus::Reviewing]),
            )
            ->when($request->filled('reason'), fn (Builder $q) => $q->where('reason', $request->string('reason')))
            ->when($request->filled('reported_id'), fn (Builder $q) => $q->where('reported_id', $request->string('reported_id')))
            ->when($request->filled('reporter_id'), fn (Builder $q) => $q->where('reporter_id', $request->string('reporter_id')))
            ->when($request->filled('from'), fn (Builder $q) => $q->where('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('created_at', '<', $request->date('to')->addDay()))
            ->with(['reporter', 'reported' => fn ($q) => $q->withCount(['reportsReceived']), 'handler'])
            ->when($request->input('sort', 'oldest') === 'newest', fn (Builder $q) => $q->latest(), fn (Builder $q) => $q->oldest())
            ->paginate(30)
            ->withQueryString();

        return ReportResource::collection($reports);
    }

    public function show(Report $report): ReportResource
    {
        return ReportResource::make($report->load(['reporter', 'reported' => fn ($q) => $q->withCount(['reportsReceived']), 'handler']));
    }

    public function update(UpdateReportRequest $request, Report $report): ReportResource
    {
        $previous = $report->status;
        $report->forceFill([
            'status' => $request->enum('status', ReportStatus::class),
            'resolution_note' => $request->input('resolution_note'),
            'handled_by' => $request->user()->id,
        ])->save();

        $this->audit->record('report.updated', $report, ['from' => $previous->value, 'to' => $report->status->value, 'reported_id' => $report->reported_id]);

        return ReportResource::make($report->load(['reporter', 'reported' => fn ($q) => $q->withCount(['reportsReceived']), 'handler']));
    }
}
