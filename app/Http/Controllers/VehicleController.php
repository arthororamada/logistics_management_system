<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleGroup;
use App\Models\VehicleMake;
use App\Models\VehicleStatus;
use App\Models\VehicleType;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class VehicleController extends Controller
{
    private const IMPORT_FIELDS = ['warehouse_code', 'vehicle_group', 'body_number', 'vehicle_type', 'plate_number', 'vehicle_make', 'year_model', 'engine_number', 'chassi_number', 'transmission', 'fuel_type', 'fuel_capacity', 'fuel_usage_per_kilometer', 'engine_type', 'cargo_capacity', 'battery_size', 'tire_size', 'sunsetting_year', 'date_acquired', 'registration_number', 'mv_file_number', 'odometer', 'vehicle_status', 'remarks'];

    public function index(Request $request): View|JsonResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'warehouse_code' => ['nullable', 'string', 'max:30'],
            'vehicle_group' => ['nullable', 'string', 'max:100'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
        ]);
        if ($request->expectsJson() || $request->ajax()) return $this->dataTable($request);

        $statuses = VehicleStatus::orderBy('vehicle_status')->get();
        $warehouses = Warehouse::where('is_active', true)->orderBy('warehouse_code')->get();
        $vehicleGroups = VehicleGroup::where('is_active', true)->orderBy('vehicle_group_description')->get();
        $vehicleTypes = VehicleType::where('is_active', true)->orderBy('vehicle_type')->get();
        $statusColors = $statuses->pluck('color', 'vehicle_status');
        return view('vehicles.index', compact('filters', 'statuses', 'warehouses', 'vehicleGroups', 'vehicleTypes', 'statusColors'));
    }

    private function dataTable(Request $request): JsonResponse
    {
        $query = Vehicle::query();
        $total = (clone $query)->count();
        $search = trim((string) $request->input('search.value', $request->input('search', '')));
        if ($search !== '') $query->where(fn ($builder) => $builder->where('plate_number', 'like', "%{$search}%")->orWhere('body_number', 'like', "%{$search}%")->orWhere('vehicle_make', 'like', "%{$search}%")->orWhere('vehicle_type', 'like', "%{$search}%"));
        foreach (['status' => 'vehicle_status', 'warehouse_code' => 'warehouse_code', 'vehicle_group' => 'vehicle_group', 'vehicle_type' => 'vehicle_type'] as $input => $column) if ($request->input($input)) $query->where($column, $request->input($input));
        $filtered = (clone $query)->count();
        $columns = ['plate_number', 'vehicle_type', 'vehicle_make', 'warehouse_code', 'vehicle_status'];
        $orderColumn = $columns[(int) $request->input('order.0.column', 0)] ?? 'plate_number';
        $orderDirection = strtolower((string) $request->input('order.0.dir', 'asc')) === 'desc' ? 'desc' : 'asc';
        $length = min(max((int) $request->input('length', 15), 1), 100);
        $start = max((int) $request->input('start', 0), 0);
        $statusColors = VehicleStatus::pluck('color', 'vehicle_status');
        $vehicles = $query->orderBy($orderColumn, $orderDirection)->offset($start)->limit($length)->get();
        $canEdit = $request->user()?->hasPermission('vehicles.update');
        $data = $vehicles->map(function (Vehicle $vehicle) use ($statusColors, $canEdit) {
            $color = $statusColors[$vehicle->vehicle_status] ?? '#0B1F3A';
            return ['vehicle' => e($vehicle->vehicle_make).' '.e($vehicle->year_model).' <small>'.e($vehicle->body_number).'</small>', 'vehicle_type' => e($vehicle->vehicle_type).' <small>'.e($vehicle->vehicle_group).'</small>', 'warehouse_code' => e($vehicle->warehouse_code), 'plate_number' => e($vehicle->plate_number), 'status' => '<span class="status-badge status-colored" style="color: '.$color.'; background: '.$color.'1A">'.e($vehicle->vehicle_status).'</span>', 'actions' => '<a href="'.route('vehicles.show', $vehicle).'" class="table-action">Show</a>'.($canEdit ? ' <a href="'.route('vehicles.edit', $vehicle).'" class="table-action">Edit</a>' : '')];
        });
        return response()->json(['draw' => (int) $request->input('draw', 0), 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'data' => $data]);
    }

    public function create(): View { return view('vehicles.create', $this->formData(new Vehicle())); }

    public function export(Request $request)
    {
        return $this->xlsxDownload('vehicles-'.now()->format('Ymd-His').'.xlsx', function ($handle) use ($request) {
            $this->writeXlsxRow($handle, self::IMPORT_FIELDS);
            $this->filteredVehicleQuery($request)->orderBy('id')->cursor()->each(fn (Vehicle $vehicle) => $this->writeXlsxRow($handle, array_map(fn ($field) => $vehicle->{$field}, self::IMPORT_FIELDS)));
        });
    }

    public function template()
    {
        return $this->xlsxDownload('vehicles-upload-template.xlsx', function ($handle) {
            $this->writeXlsxRow($handle, self::IMPORT_FIELDS);
            $this->writeXlsxRow($handle, array_fill(0, count(self::IMPORT_FIELDS), ''));
        });
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:51200']]);
        $rows = $this->readImportRows($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalExtension());
        if (empty($rows)) return back()->with('error', 'The uploaded file does not contain any vehicle rows.');
        $errors = []; $validRows = [];
        foreach ($rows as $line => $row) {
            $row = array_replace(array_fill_keys(self::IMPORT_FIELDS, null), array_intersect_key($row, array_flip(self::IMPORT_FIELDS)));
            $validator = Validator::make($row, $this->vehicleRules());
            if ($validator->fails()) { $errors[] = 'Row '.($line + 2).': '.implode(' ', $validator->errors()->all()); continue; }
            $validRows[] = $row;
        }
        if ($errors) return back()->with('error', 'Upload failed: '.implode(' | ', array_slice($errors, 0, 10)));
        DB::transaction(fn () => collect($validRows)->chunk(500)->each(fn ($chunk) => Vehicle::insert($chunk->map(fn ($row) => [...$row, 'created_at' => now(), 'updated_at' => now()])->all())));
        return redirect()->route('vehicles.index')->with('success', count($validRows).' vehicle(s) uploaded successfully.');
    }

    public function store(Request $request): RedirectResponse
    {
        Vehicle::create($this->validateVehicle($request));
        return redirect()->route('vehicles.index')->with('success', 'Vehicle created successfully.');
    }

    public function edit(Vehicle $vehicle): View { return view('vehicles.edit', $this->formData($vehicle)); }

    public function show(Vehicle $vehicle): View
    {
        $maintenances = $vehicle->maintenances()
            ->with('policy:id,maintenance_code,maintenance_description')
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->get();
        $maintenances->each(function ($maintenance) use ($vehicle) {
            $maintenance->setRelation('vehicle', $vehicle);
            $maintenance->setAttribute('current_status', $maintenance->status());
        });
        $maintenanceCounts = [
            'normal' => $maintenances->where('current_status', 'normal')->count(),
            'warning' => $maintenances->where('current_status', 'warning')->count(),
            'violation' => $maintenances->where('current_status', 'violation')->count(),
        ];

        return view('vehicles.show', compact('vehicle', 'maintenances', 'maintenanceCounts'));
    }

    public function update(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $vehicle->update($this->validateVehicle($request, $vehicle));
        return redirect()->route('vehicles.index')->with('success', 'Vehicle updated successfully.');
    }

    private function formData(Vehicle $vehicle): array
    {
        $activeOrSelected = fn ($query, $column, $value) => $query->where(fn ($builder) => $builder->where('is_active', true)->orWhere($column, $value));
        $warehouses = $activeOrSelected(Warehouse::query(), 'warehouse_code', $vehicle->warehouse_code)->orderBy('warehouse_code')->get();
        $vehicleGroups = $activeOrSelected(VehicleGroup::query(), 'vehicle_group_description', $vehicle->vehicle_group)->orderBy('vehicle_group_description')->get();
        $vehicleTypes = $activeOrSelected(VehicleType::query(), 'vehicle_type', $vehicle->vehicle_type)->orderBy('vehicle_type')->get();
        $vehicleMakes = $activeOrSelected(VehicleMake::query(), 'vehicle_make', $vehicle->vehicle_make)->orderBy('vehicle_make')->get();
        $vehicleStatuses = $activeOrSelected(VehicleStatus::query(), 'vehicle_status', $vehicle->vehicle_status)->orderBy('vehicle_status')->get();
        return compact('vehicle', 'warehouses', 'vehicleGroups', 'vehicleTypes', 'vehicleMakes', 'vehicleStatuses');
    }

    private function validateVehicle(Request $request, ?Vehicle $vehicle = null): array
    {
        foreach (['warehouse_code', 'vehicle_group', 'body_number', 'vehicle_type', 'plate_number', 'vehicle_make', 'engine_number', 'chassi_number', 'transmission', 'fuel_type', 'engine_type', 'battery_size', 'tire_size', 'registration_number', 'mv_file_number', 'vehicle_status'] as $field) {
            $request->merge([$field => $request->input($field) === null ? null : trim((string) $request->input($field))]);
        }
        return $request->validate($this->vehicleRules($vehicle));
    }

    private function vehicleRules(?Vehicle $vehicle = null): array
    {
        return [
            'warehouse_code' => ['required', 'string', 'max:30', Rule::exists('warehouses', 'warehouse_code')],
            'vehicle_group' => ['required', 'string', 'max:100', Rule::exists('vehicle_groups', 'vehicle_group_description')],
            'body_number' => ['required', 'string', 'max:100', Rule::unique('vehicles', 'body_number')->ignore($vehicle)],
            'vehicle_type' => ['required', 'string', 'max:100', Rule::exists('vehicle_types', 'vehicle_type')],
            'plate_number' => ['required', 'string', 'max:30', Rule::unique('vehicles', 'plate_number')->ignore($vehicle)],
            'vehicle_make' => ['required', 'string', 'max:100', Rule::exists('vehicle_makes', 'vehicle_make')],
            'year_model' => ['required', 'integer', 'between:1900,2200'],
            'engine_number' => ['required', 'string', 'max:100'],
            'chassi_number' => ['required', 'string', 'max:100'],
            'transmission' => ['required', 'string', 'max:50'],
            'fuel_type' => ['required', 'string', 'max:50'],
            'fuel_capacity' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'fuel_usage_per_kilometer' => ['required', 'numeric', 'min:0', 'max:9999999.999'],
            'engine_type' => ['required', 'string', 'max:100'],
            'cargo_capacity' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'battery_size' => ['nullable', 'string', 'max:50'],
            'tire_size' => ['nullable', 'string', 'max:50'],
            'sunsetting_year' => ['nullable', 'integer', 'between:1900,2200'],
            'date_acquired' => ['nullable', 'date'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'mv_file_number' => ['nullable', 'string', 'max:100'],
            'odometer' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'vehicle_status' => ['required', 'string', 'max:100', Rule::exists('vehicle_statuses', 'vehicle_status')],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function filteredVehicleQuery(Request $request)
    {
        $query = Vehicle::query();
        if ($request->input('search')) $query->where(fn ($builder) => $builder->where('plate_number', 'like', '%'.$request->input('search').'%')->orWhere('body_number', 'like', '%'.$request->input('search').'%')->orWhere('vehicle_make', 'like', '%'.$request->input('search').'%')->orWhere('vehicle_type', 'like', '%'.$request->input('search').'%'));
        foreach (['status' => 'vehicle_status', 'warehouse_code' => 'warehouse_code', 'vehicle_group' => 'vehicle_group', 'vehicle_type' => 'vehicle_type'] as $input => $column) if ($request->input($input)) $query->where($column, $request->input($input));
        return $query;
    }

    private function readImportRows(string $path, string $extension): array
    {
        if (strtolower($extension) !== 'xlsx') {
            $handle = fopen($path, 'rb'); $headers = fgetcsv($handle); $rows = [];
            while (($values = fgetcsv($handle)) !== false) if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) > 0) $rows[] = array_combine($headers, array_pad($values, count($headers), null));
            fclose($handle); return $rows;
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return [];
        $shared = []; if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) { $sharedXml = simplexml_load_string($xml); foreach ($sharedXml->si as $item) $shared[] = (string) ($item->t ?? implode('', array_map('strval', $item->r->t ?? []))); }
        $sheet = simplexml_load_string((string) $zip->getFromName('xl/worksheets/sheet1.xml')); $zip->close(); $rows = [];
        foreach ($sheet->sheetData->row as $rowIndex => $row) { if ($rowIndex === 0) continue; $values = array_fill(0, count(self::IMPORT_FIELDS), null); foreach ($row->c as $cell) { $reference = (string) $cell['r']; preg_match('/^[A-Z]+/', $reference, $match); $column = 0; foreach (str_split($match[0] ?? '') as $letter) $column = $column * 26 + ord($letter) - 64; $column--; $value = (string) ($cell->v ?? ''); if ((string) $cell['t'] === 's') $value = $shared[(int) $value] ?? ''; if ((string) $cell['t'] === 'inlineStr') $value = (string) ($cell->is->t ?? ''); if ($column >= 0 && $column < count($values)) $values[$column] = $value; } if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) > 0) $rows[] = array_combine(self::IMPORT_FIELDS, $values); }
        return $rows;
    }

    private function xlsxDownload(string $filename, callable $writer)
    {
        return response()->streamDownload(function () use ($writer) {
            $sheetPath = tempnam(sys_get_temp_dir(), 'vehicle-sheet-'); $handle = fopen($sheetPath, 'wb'); fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
            $writer($handle); fwrite($handle, '</sheetData></worksheet>'); fclose($handle);
            $zipPath = tempnam(sys_get_temp_dir(), 'vehicle-xlsx-'); $zip = new \ZipArchive(); $zip->open($zipPath); $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>'); $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>'); $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Vehicles" sheetId="1" r:id="rId1"/></sheets></workbook>'); $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>'); $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml'); $zip->close(); readfile($zipPath); @unlink($sheetPath); @unlink($zipPath);
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function writeXlsxRow($handle, array $values): void
    {
        static $row = 0; $row++; fwrite($handle, '<row r="'.$row.'">'); foreach (array_values($values) as $index => $value) { $column = ''; $number = $index + 1; while ($number > 0) { $remainder = ($number - 1) % 26; $column = chr(65 + $remainder).$column; $number = intdiv($number - 1, 26); } $text = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8'); fwrite($handle, '<c r="'.$column.$row.'" t="inlineStr"><is><t>'.$text.'</t></is></c>'); } fwrite($handle, '</row>');
    }
}
