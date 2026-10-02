@extends('layouts.app')
@section('title', 'Stock Levels')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Stock Levels</h4>
            <p>Multi-location finished goods inventory &mdash; Pakistan is the manufacturing master, Qatar is the retail satellite.</p>
        </div>
        @moduleEdit('ecommerce')
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#stockTransferModal">Transfer to Qatar</button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#stockSaleModal">Log Sale</button>
            </div>
        @endmoduleEdit
    </div>

    @include('ecommerce._nav')

    <div class="ec-card">
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Unit Size</th>
                        <th>Pakistan Stock (Master)</th>
                        <th>Qatar Stock (Retail)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->unit_size ?? '—' }}</td>
                            <td class="{{ $product->stock_pakistan < 0 ? 'ec-negative' : '' }}">{{ number_format($product->stock_pakistan, 2) }}</td>
                            <td class="{{ $product->stock_qatar < 0 ? 'ec-negative' : '' }}">{{ number_format($product->stock_qatar, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No finished goods yet &mdash; add some in Inventory &amp; Production first.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @moduleEdit('ecommerce')
        {{-- Transfer to Qatar Modal --}}
        <div class="modal fade" id="stockTransferModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.stock.transfer') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Transfer to Qatar</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Finished Product</label>
                                <select name="ecommerce_product_id" class="form-select" required>
                                    <option value="" disabled selected>Select product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }} &mdash; {{ number_format($product->stock_pakistan, 2) }} in Pakistan</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" placeholder="e.g. 10" required>
                            </div>
                            <p class="ec-sub mb-0">Moves stock from Pakistan to Qatar. Cannot exceed what's currently available in Pakistan.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Transfer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Log Sale Modal --}}
        <div class="modal fade" id="stockSaleModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.stock.sale') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Log Sale</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Finished Product</label>
                                <select name="ecommerce_product_id" class="form-select" required>
                                    <option value="" disabled selected>Select product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" placeholder="e.g. 5" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Sales Channel</label>
                                <select name="sales_channel" class="form-select" required>
                                    <option value="" disabled selected>Select channel</option>
                                    <option value="Shopify (Pakistan)">Shopify (Pakistan)</option>
                                    <option value="Organic (Pakistan)">Organic (Pakistan)</option>
                                    <option value="Salon (Qatar)">Salon (Qatar)</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endmoduleEdit
@endsection
