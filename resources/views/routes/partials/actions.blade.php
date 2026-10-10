<div class="row-actions">
    @if($canEdit)<a href="{{ route('routes.edit', $route) }}" class="table-action">Edit</a>@endif
    @if($canDelete)<form method="POST" action="{{ route('routes.destroy', $route) }}" data-confirm="Delete this route? This action cannot be undone." data-confirm-title="Delete record?" data-confirm-button="Delete">@csrf @method('DELETE')<button type="submit" class="table-action table-action-danger">Delete</button></form>@endif
</div>
