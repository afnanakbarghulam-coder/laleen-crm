@extends('layouts.app')
@section('title', 'Outbound & Usage')

@include('ecommerce._styles')

@section('content')
    <div class="ec-header">
        <div>
            <h4>Outbound &amp; Usage</h4>
            <p>Finished goods leaving inventory &mdash; retail sales, backbar use, promos, and damage/expiry.</p>
        </div>
        @moduleEdit('ecommerce')
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#outboundModal">+ Log Outbound Stock</button>
        @endmoduleEdit
    </div>

    @include('ecommerce._nav')

    <div class="ec-card">
        <div class="table-responsive">
            <table class="table ec-table align-middle">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Product</th>
                        <th>Quantity Removed</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($outbounds as $outbound)
                        <tr>
                            <td>{{ $outbound->created_at->format('d M Y') }}</td>
                            <td>{{ $outbound->product->name ?? '—' }}</td>
                            <td>{{ $outbound->quantity }}</td>
                            <td><span class="ec-badge" style="background: rgba(217,143,131,0.14); color: var(--ec-ink);">{{ $outbound->reason }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted">No outbound stock logged yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @moduleEdit('ecommerce')
        {{-- Log Outbound Stock Modal --}}
        <div class="modal fade" id="outboundModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('ecommerce.outbound.store') }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Log Outbound Stock</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Product</label>
                                <select name="ecommerce_product_id" class="form-select" required>
                                    <option value="" disabled selected>Select product</option>
                                    @foreach ($products as $product)
                                        <option value="{{ $product->id }}">{{ $product->name }}{{ $product->sku ? ' (' . $product->sku . ')' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="number" step="1" min="1" name="quantity" class="form-control" placeholder="e.g. 5" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Reason</label>
                                <select name="reason" class="form-select" required>
                                    <option value="" disabled selected>Select a reason</option>
                                    @foreach ($reasons as $reason)
                                        <option value="{{ $reason }}">{{ $reason }}</option>
                                    @endforeach
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

        <script>
            document.getElementById('outboundModal').addEventListener('hidden.bs.modal', function () {
                this.querySelector('form').reset();
            });
        </script>
    @endmoduleEdit
@endsection
