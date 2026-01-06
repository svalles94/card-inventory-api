<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cards Grid - Inventory Architect</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .card-image {
            transition: transform 0.2s;
        }
        .card-image:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold mb-2">Cards Grid</h1>
                <p class="text-gray-400">Location: <span class="text-white font-semibold">{{ $location->name ?? 'Not Set' }}</span></p>
            </div>
            <a href="{{ route('filament.store.resources.store-cards.index') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg transition">
                Back to List View
            </a>
        </div>

        <div id="cards-container" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
            @foreach($cards as $card)
                <div class="card-item bg-gray-800 rounded-lg p-3 hover:bg-gray-750 transition cursor-pointer" 
                     data-card-id="{{ $card['id'] }}"
                     data-edition-id="{{ $card['edition_id'] ?? '' }}">
                    <div class="relative">
                        <img src="{{ $card['image_url'] ?? $card['edition_image_url'] ?? '/images/card-placeholder.png' }}" 
                             alt="{{ $card['name'] }}"
                             class="card-image w-full max-w-[180px] h-auto rounded-lg mx-auto block">
                        @if($card['in_stock'])
                            <span class="absolute top-2 right-2 bg-green-600 text-white text-xs font-bold px-2 py-1 rounded">
                                In Stock
                            </span>
                        @endif
                    </div>
                    <div class="mt-2 text-center">
                        <p class="text-sm font-semibold truncate" title="{{ $card['name'] }}">{{ $card['name'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        @if(count($cards) === 0)
            <div class="text-center py-12">
                <p class="text-gray-400 text-lg">No cards found</p>
            </div>
        @endif
    </div>

    <!-- Queue Modal -->
    <div id="queue-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-gray-800 rounded-lg p-6 max-w-md w-full mx-4">
            <h2 class="text-2xl font-bold mb-4">Add to Update Queue</h2>
            <form id="queue-form">
                <input type="hidden" id="queue-card-id" name="card_id">
                <input type="hidden" id="queue-edition-id" name="edition_id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Quantity to Add</label>
                    <input type="number" 
                           id="queue-quantity" 
                           name="delta_quantity" 
                           min="1" 
                           value="1" 
                           required
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Foil</label>
                    <select id="queue-foil" name="is_foil" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                        <option value="0">Normal</option>
                        <option value="1">Foil</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Sell Price (optional)</label>
                    <input type="number" 
                           id="queue-sell-price" 
                           name="sell_price" 
                           step="0.01" 
                           min="0"
                           class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2 text-white">
                </div>

                <div class="flex gap-3">
                    <button type="submit" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded transition">
                        Add to Queue
                    </button>
                    <button type="button" 
                            onclick="closeQueueModal()" 
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 rounded transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Handle card click
        document.querySelectorAll('.card-item').forEach(card => {
            card.addEventListener('click', function() {
                const cardId = this.dataset.cardId;
                const editionId = this.dataset.editionId || '';
                
                document.getElementById('queue-card-id').value = cardId;
                document.getElementById('queue-edition-id').value = editionId;
                document.getElementById('queue-modal').classList.remove('hidden');
            });
        });

        function closeQueueModal() {
            document.getElementById('queue-modal').classList.add('hidden');
        }

        // Handle form submission
        document.getElementById('queue-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = {
                card_id: formData.get('card_id'),
                edition_id: formData.get('edition_id'),
                delta_quantity: parseInt(formData.get('delta_quantity')),
                is_foil: formData.get('is_foil') === '1',
                sell_price: formData.get('sell_price') || null,
            };

            try {
                const response = await fetch('{{ route("store.cards.add-to-queue") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(data),
                });

                const result = await response.json();

                if (response.ok) {
                    alert('Card added to queue successfully!');
                    closeQueueModal();
                } else {
                    alert('Error: ' + (result.error || 'Failed to add card to queue'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            }
        });

        // Close modal on outside click
        document.getElementById('queue-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeQueueModal();
            }
        });
    </script>
</body>
</html>

