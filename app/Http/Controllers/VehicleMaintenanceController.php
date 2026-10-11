<?php

namespace App\Http\Controllers;

use App\Models\VehicleGroup;
use App\Models\VehicleMaintenance;
use App\Models\VehicleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VehicleMaintenanceController extends Controller
{
    private const IMPORT_FIELDS = [
        'maintenance_code', 'maintenance_description', 'vehicle_group', 'vehicle_type',
        'maintenance_type', 'threshold', 'threshold_warning', 'is_active',
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'maintenance_type' => ['nullable', Rule::in([VehicleMaintenance::DISTANCE_POLICY, VehicleMaintenance::DATE_POLICY])],
            'vehicle_group' => ['nullable', 'string', 'max:150'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
        ]);

        $maintenances = VehicleMaintenance::query()
            ->with(['creator:id,name', 'updater:id,name'])
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(fn ($builder) => $builder
                ->where('maintenance_code', 'like', "%{$search}%")
                ->orWhere('maintenance_description', 'like', "%{$search}%")))
            ->when($filters['vehicle_group'] ?? null, fn ($query, $value) => $query->where('vehicle_group', $value))
            ->when($filters['vehicle_type'] ?? null, fn ($query, $value) => $query->where('vehicle_type', $value))
            ->when($filters['maintenance_type'] ?? null, fn ($query, $value) => $query->where('maintenance_type', $value))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('maintenance_code')
            ->paginate(15)
            ->withQueryString();

        $vehicleGroups = VehicleGroup::orderBy('vehicle_group_description')->get();
        $vehicleTypes = VehicleType::orderBy('vehicle_type')->get();

        return view('vehicle-maintenances.index', compact('maintenances', 'filters', 'vehicleGroups', 'vehicleTypes'));
    }

    public function create(): View
    {
        return view('vehicle-maintenances.create', $this->formData(new VehicleMaintenance()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMaintenance($request);
        $data['created_by'] = $request->user()->id;
        $data['update_by'] = $request->user()->id;
        VehicleMaintenance::create($data);

        return redirect()->route('vehicle-maintenances.index')->with('success', 'Vehicle maintenance policy created successfully.');
    }

    public function edit(VehicleMaintenance $vehicleMaintenance): View
    {
        return view('vehicle-maintenances.edit', $this->formData($vehicleMaintenance));
    }

    public function update(Request $request, VehicleMaintenance $vehicleMaintenance): RedirectResponse
    {
        $data = $this->validateMaintenance($request, $vehicleMaintenance);
        $data['update_by'] = $request->user()->id;
        $vehicleMaintenance->update($data);

        return redirect()->route('vehicle-maintenances.index')->with('success', 'Vehicle maintenance policy updated successfully.');
    }

    public function destroy(VehicleMaintenance $vehicleMaintenance): RedirectResponse
    {
        $vehicleMaintenance->delete();

        return back()->with('success', 'Vehicle maintenance policy deleted successfully.');
    }

    public function template()
    {
        return $this->xlsxDownload('vehicle-maintenance-upload-template.xlsx', function ($handle) {
            $this->writeXlsxRow($handle, self::IMPORT_FIELDS, 1);
            $this->writeXlsxRow($handle, ['OIL_CHANGE', 'Engine oil and filter change', 'Delivery Vans', 'Van', 'distance_policy', '10000', '1000', '1'], 2);
        });
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:51200']]);

        try {
            $rows = $this->readImportRows($request->file('file')->getRealPath(), $request->file('file')->getClientOriginalExtension());
        } catch (\Throwable $exception) {
            return back()->with('error', 'The uploaded file could not be read. Please use the provided template.');
        }

        if ($rows === []) {
            return back()->with('error', 'The uploaded file does not contain any maintenance rows.');
        }

        $errors = [];
        $validRows = [];
        $seenCodes = [];
        foreach ($rows as $line => $row) {
            $row = array_replace(array_fill_keys(self::IMPORT_FIELDS, null), array_intersect_key($row, array_flip(self::IMPORT_FIELDS)));
            $row = $this->normalizeData($row);
            $code = $row['maintenance_code'];
            if ($code !== '' && isset($seenCodes[$code])) {
                $errors[] = 'Row '.($line + 2).": maintenance_code {$code} is duplicated in the upload.";
                continue;
            }
            $existing = $code === '' ? null : VehicleMaintenance::where('maintenance_code', $code)->first();
            $validator = Validator::make($row, $this->rules($existing));
            if ($validator->fails()) {
                $errors[] = 'Row '.($line + 2).': '.implode(' ', $validator->errors()->all());
                continue;
            }
            $seenCodes[$code] = true;
            $validRows[] = $validator->validated();
        }

        if ($errors !== []) {
            return back()->with('error', 'Upload failed. No rows were saved. '.implode(' | ', array_slice($errors, 0, 10)));
        }

        $userId = $request->user()->id;
        DB::transaction(function () use ($validRows, $userId) {
            foreach ($validRows as $row) {
                $maintenance = VehicleMaintenance::where('maintenance_code', $row['maintenance_code'])->first();
                if ($maintenance) {
                    $maintenance->update([...$row, 'update_by' => $userId]);
                } else {
                    VehicleMaintenance::create([...$row, 'created_by' => $userId, 'update_by' => $userId]);
                }
            }
        });

        return redirect()->route('vehicle-maintenances.index')->with('success', count($validRows).' vehicle maintenance policy record(s) uploaded successfully.');
    }

    private function formData(VehicleMaintenance $vehicleMaintenance): array
    {
        $activeOrSelected = fn ($query, $column, $value) => $query->where(fn ($builder) => $builder->where('is_active', true)->orWhere($column, $value));
        $vehicleGroups = $activeOrSelected(VehicleGroup::query(), 'vehicle_group_description', $vehicleMaintenance->vehicle_group)->orderBy('vehicle_group_description')->get();
        $vehicleTypes = $activeOrSelected(VehicleType::query(), 'vehicle_type', $vehicleMaintenance->vehicle_type)->orderBy('vehicle_type')->get();

        return compact('vehicleMaintenance', 'vehicleGroups', 'vehicleTypes');
    }

    private function validateMaintenance(Request $request, ?VehicleMaintenance $maintenance = null): array
    {
        $data = $this->normalizeData($request->only(self::IMPORT_FIELDS));
        $request->merge($data);

        return $request->validate($this->rules($maintenance));
    }

    private function normalizeData(array $data): array
    {
        $data['maintenance_code'] = strtoupper(trim((string) ($data['maintenance_code'] ?? '')));
        foreach (['maintenance_description', 'vehicle_group', 'vehicle_type'] as $field) {
            $data[$field] = trim((string) ($data[$field] ?? ''));
        }
        $policy = strtolower(trim((string) ($data['maintenance_type'] ?? '')));
        $data['maintenance_type'] = str_replace([' ', '-'], '_', $policy);
        $active = strtolower(trim((string) ($data['is_active'] ?? '')));
        $data['is_active'] = in_array($active, ['1', 'true', 'yes', 'active', 'on'], true) ? 1 : (in_array($active, ['0', 'false', 'no', 'inactive', 'off'], true) ? 0 : $data['is_active'] ?? null);

        return $data;
    }

    private function rules(?VehicleMaintenance $maintenance = null): array
    {
        return [
            'maintenance_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('vehicle_maintenances', 'maintenance_code')->ignore($maintenance)],
            'maintenance_description' => ['required', 'string', 'max:150'],
            'vehicle_group' => ['required', 'string', 'max:150', Rule::exists('vehicle_groups', 'vehicle_group_description')],
            'vehicle_type' => ['required', 'string', 'max:100', Rule::exists('vehicle_types', 'vehicle_type')],
            'maintenance_type' => ['required', Rule::in([VehicleMaintenance::DISTANCE_POLICY, VehicleMaintenance::DATE_POLICY])],
            'threshold' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'threshold_warning' => ['required', 'integer', 'min:0', 'lte:threshold', 'max:4294967295'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function readImportRows(string $path, string $extension): array
    {
        if (strtolower($extension) !== 'xlsx') {
            $handle = fopen($path, 'rb');
            if ($handle === false) return [];
            $headers = fgetcsv($handle);
            if ($headers === false) { fclose($handle); return []; }
            $headers = array_map(fn ($header) => strtolower(trim((string) $header)), $headers);
            $rows = [];
            while (($values = fgetcsv($handle)) !== false) {
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) continue;
                $values = array_slice(array_pad($values, count($headers), null), 0, count($headers));
                $rows[] = array_combine($headers, $values);
            }
            fclose($handle);
            return $rows;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return [];
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sharedXml = simplexml_load_string($xml);
            foreach ($sharedXml->si as $item) $shared[] = (string) ($item->t ?? implode('', array_map('strval', $item->r->t ?? [])));
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) return [];
        $sheet = simplexml_load_string($sheetXml);
        $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                preg_match('/^[A-Z]+/', (string) $cell['r'], $match);
                $column = 0;
                foreach (str_split($match[0] ?? '') as $letter) $column = $column * 26 + ord($letter) - 64;
                $value = (string) ($cell->v ?? '');
                if ((string) $cell['t'] === 's') $value = $shared[(int) $value] ?? '';
                if ((string) $cell['t'] === 'inlineStr') $value = (string) ($cell->is->t ?? '');
                if ($column > 0) $values[$column - 1] = $value;
            }
            if ($values !== []) {
                $width = max(array_keys($values)) + 1;
                $matrix[] = array_replace(array_fill(0, $width, null), $values);
            }
        }
        if ($matrix === []) return [];
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), array_shift($matrix));
        $rows = [];
        foreach ($matrix as $values) {
            $values = array_slice(array_pad($values, count($headers), null), 0, count($headers));
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) > 0) $rows[] = array_combine($headers, $values);
        }
        return $rows;
    }

    private function xlsxDownload(string $filename, callable $writer)
    {
        return response()->streamDownload(function () use ($writer) {
            $sheetPath = tempnam(sys_get_temp_dir(), 'maintenance-sheet-');
            $handle = fopen($sheetPath, 'wb');
            fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
            $writer($handle);
            fwrite($handle, '</sheetData></worksheet>');
            fclose($handle);
            $zipPath = tempnam(sys_get_temp_dir(), 'maintenance-xlsx-');
            $zip = new \ZipArchive();
            $zip->open($zipPath);
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Maintenance Policies" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $zip->addFile($sheetPath, 'xl/worksheets/sheet1.xml');
            $zip->close();
            readfile($zipPath);
            @unlink($sheetPath);
            @unlink($zipPath);
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private function writeXlsxRow($handle, array $values, int $row): void
    {
        fwrite($handle, '<row r="'.$row.'">');
        foreach (array_values($values) as $index => $value) {
            $column = ''; $number = $index + 1;
            while ($number > 0) { $remainder = ($number - 1) % 26; $column = chr(65 + $remainder).$column; $number = intdiv($number - 1, 26); }
            $text = htmlspecialchars((string) ($value ?? ''), ENT_XML1 | ENT_COMPAT, 'UTF-8');
            fwrite($handle, '<c r="'.$column.$row.'" t="inlineStr"><is><t>'.$text.'</t></is></c>');
        }
        fwrite($handle, '</row>');
    }
}
