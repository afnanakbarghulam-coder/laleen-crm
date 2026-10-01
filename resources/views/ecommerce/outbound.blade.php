@extends('layouts.app')
@section('title', 'Sales & Usage')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Sales &amp; Usage</h4>
            <p>Finished goods leaving inventory &mdash; retail sales, backbar use, promos, and damage/expiry.</p>
        </div>
        @moduleEdit('ecommerce')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#outboundModal">+ Log Sales &amp; Usage</button>
        @endmoduleEdit
    </div>

    @include('ecommerce._nav')

    <div class="ec-card">
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer Name</th>
                        <th>Product</th>
                        <th>Quantity Removed</th>
                        <th>Price</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outbounds as $outbound)
                        <tr>
                            <td>{{ $outbound->created_at->format('d M Y') }}</td>
                            <td>{{ $outbound->customer_name ?? '—' }}</td>
                            <td>{{ $outbound->product->name ?? '—' }}</td>
                            <td>{{ $outbound->quantity }}</td>
                            <td>{{ $outbound->price !== null ? 'PKR ' . number_format($outbound->price, 2) : '—' }}</td>
                            <td><span class="ec-badge" style="background: rgba(217,143,131,0.14); color: var(--ec-ink);">{{ $outbound->reason }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted">No sales or usage logged yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @moduleEdit('ecommerce')
        {{-- Log Sales & Usage Modal --}}
        <div class="modal fade" id="outboundModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.outbound.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Log Sales &amp; Usage</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Customer Name</label>
                                    <input type="text" name="customer_name" class="form-control" placeholder="Optional">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" name="contact_number" class="form-control" placeholder="Optional">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <select name="reason" id="outboundReasonSelect" class="form-select">
                                    <option value="" selected>Select a reason</option>
                                    @foreach ($reasons as $reason)
                                        <option value="{{ $reason }}">{{ $reason }}</option>
                                    @endforeach
                                    <option value="__add_new__">+ Add New Category</option>
                                </select>
                                <input type="text" id="outboundReasonCustomInput" class="form-control mt-2" placeholder="Type the new reason" style="display: none;">
                            </div>

                            <hr style="border-color: var(--ec-border);">

                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Items</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="addOutboundItemRowBtn">+ Add Item</button>
                            </div>
                            <div id="outboundItemRows"></div>
                            @if ($products->isEmpty())
                                <p class="ec-sub mb-0">No products yet &mdash; add some in Inventory &amp; Production first.</p>
                            @endif
                        </div>
                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const outboundReasonSelect = document.getElementById('outboundReasonSelect');
            const outboundReasonCustomInput = document.getElementById('outboundReasonCustomInput');

            outboundReasonSelect.addEventListener('change', function () {
                if (this.value === '__add_new__') {
                    outboundReasonCustomInput.style.display = 'block';
                    outboundReasonCustomInput.name = 'reason';
                    outboundReasonCustomInput.required = true;
                    outboundReasonSelect.removeAttribute('name');
                    outboundReasonCustomInput.focus();
                } else {
                    outboundReasonCustomInput.style.display = 'none';
                    outboundReasonCustomInput.removeAttribute('name');
                    outboundReasonCustomInput.required = false;
                    outboundReasonCustomInput.value = '';
                    outboundReasonSelect.name = 'reason';
                }
            });

            const outboundProductOptionsHtml = `@foreach ($products as $product)<option value="{{ $product->id }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>@endforeach`;
            let outboundItemRowIndex = 0;

            function addOutboundItemRow() {
                const index = outboundItemRowIndex++;
                const row = document.createElement('div');
                row.className = 'row g-2 align-items-end mb-2 outbound-item-row';
                row.innerHTML =
                    '<div class="col-5">' +
                        '<select name="items[' + index + '][ecommerce_product_id]" class="form-select" required>' +
                            '<option value="" disabled selected>Select product</option>' +
                            outboundProductOptionsHtml +
                        '</select>' +
                    '</div>' +
                    '<div class="col-3">' +
                        '<input type="number" step="1" min="1" name="items[' + index + '][quantity]" class="form-control" placeholder="Quantity" required>' +
                    '</div>' +
                    '<div class="col-3">' +
                        '<input type="number" step="0.01" min="0" name="items[' + index + '][price]" class="form-control" placeholder="Price">' +
                    '</div>' +
                    '<div class="col-1">' +
                        '<button type="button" class="btn btn-outline-danger w-100 remove-outbound-item-row">&times;</button>' +
                    '</div>';

                document.getElementById('outboundItemRows').appendChild(row);

                row.querySelector('.remove-outbound-item-row').addEventListener('click', function () {
                    row.remove();
                });
            }

            document.getElementById('addOutboundItemRowBtn').addEventListener('click', addOutboundItemRow);

            document.getElementById('outboundModal').addEventListener('show.bs.modal', function () {
                if (document.getElementById('outboundItemRows').children.length === 0) {
                    addOutboundItemRow();
                }
            });

            document.getElementById('outboundModal').addEventListener('hidden.bs.modal', function () {
                this.querySelector('form').reset();
                document.getElementById('outboundItemRows').innerHTML = '';
                outboundReasonCustomInput.style.display = 'none';
                outboundReasonCustomInput.removeAttribute('name');
                outboundReasonCustomInput.required = false;
                outboundReasonCustomInput.value = '';
                outboundReasonSelect.name = 'reason';
            });
        </script>
    @endmoduleEdit
@endsection
