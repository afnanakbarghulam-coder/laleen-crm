@if (session('success'))
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-start gap-3 mt-3 flash-msg shadow-sm"
         role="alert">
        <i class="bx bx-check-circle fs-4 mt-1"></i>
         <div class="fw-semibold">
            <strong>Success:</strong> {{ session('success') }}
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('warning'))
    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-start gap-3 mt-3 flash-msg shadow-sm"
         role="alert">
        <i class="bx bx-error fs-4 mt-1"></i>
        <div class="fw-semibold">
            <strong>Attention:</strong> {{ session('warning') }}
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-3 mt-3 flash-msg shadow-sm"
         role="alert">
        <i class="bx bx-x-circle fs-4 mt-1"></i>
        <div class="fw-semibold">
            <strong>Error:</strong>
            <ul class="mb-0 ps-3">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-start gap-3 mt-3 flash-msg shadow-sm"
         role="alert">
        <i class="bx bx-x-circle fs-4 mt-1"></i>
        <div class="fw-semibold">
            <strong>Error:</strong> {{ session('error') }}
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
@endif

