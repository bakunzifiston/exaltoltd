<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMail;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderNotificationMail;
use App\Models\ContactSubmission;
use App\Models\EmailRoutingRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\Partner;
use App\Models\Product;
use App\Models\Production;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StorefrontGalleryAlbum;
use App\Models\StorefrontGalleryPhoto;
use App\Models\StorefrontVideo;
use App\Services\SaleWorkflowService;
use App\Services\StorefrontCheckoutService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

use function Illuminate\Support\defer;

class StorefrontController extends Controller
{
    public function home(): View
    {
        $bestSellers = $this->bestSellerProducts(6);
        $bestSellerIds = $bestSellers->pluck('id')->all();
        $videos = StorefrontVideo::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->latest('id')
            ->limit(6)
            ->get();
        $partners = Partner::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $galleryAlbums = $this->homeGalleryAlbums();

        return view('storefront.home', compact('bestSellers', 'bestSellerIds', 'videos', 'partners', 'galleryAlbums'));
    }

    public function about(): View
    {
        return view('storefront.about');
    }

    public function process(): View
    {
        return view('storefront.process');
    }

    public function distribution(): View
    {
        return view('storefront.distribution');
    }

    public function sustainability(): View
    {
        return view('storefront.sustainability');
    }

    public function gallery(): View
    {
        $albums = $this->storefrontGalleryAlbumsQuery()->get();

        return view('storefront.gallery', compact('albums'));
    }

    public function galleryAlbum(StorefrontGalleryAlbum $album): View
    {
        abort_unless($album->is_active, 404);

        $album->load(['photos' => fn ($query) => $query
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id'),
        ]);

        return view('storefront.gallery-album', compact('album'));
    }

    public function shop(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $category = trim((string) $request->query('category', ''));
        $price_min = trim((string) $request->query('price_min', ''));
        $price_max = trim((string) $request->query('price_max', ''));
        $priceMin = $price_min !== '' ? (float) $price_min : null;
        $priceMax = $price_max !== '' ? (float) $price_max : null;
        $order = (string) $request->query('order', 'name');
        $layout = $request->query('layout') === 'list' ? 'list' : 'grid';

        $allowedOrders = ['name', 'newest', 'popular', 'price_asc', 'price_desc'];
        if (!in_array($order, $allowedOrders, true)) {
            $order = 'name';
        }

        $query = $this->catalogQuery()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $inner) use ($search): void {
                    $inner->where('products.name', 'like', "%{$search}%")
                        ->orWhere('products.type', 'like', "%{$search}%")
                        ->orWhere('products.description', 'like', "%{$search}%")
                        ->orWhere('products.barcode', 'like', "%{$search}%");
                });
            })
            ->when($category !== '', fn (Builder $query) => $query->where('products.type', $category));

        if ($priceMin !== null) {
            $query->where('products.price', '>=', $priceMin);
        }

        if ($priceMax !== null) {
            $query->where('products.price', '<=', $priceMax);
        }

        switch ($order) {
            case 'newest':
                $query->orderBy('products.created_at', 'desc')->orderBy('products.name');
                break;
            case 'popular':
                $query->orderByRaw('sold_units DESC')->orderBy('products.name');
                break;
            case 'price_asc':
                $query->orderBy('products.price', 'asc')->orderBy('products.name');
                break;
            case 'price_desc':
                $query->orderBy('products.price', 'desc')->orderBy('products.name');
                break;
            default:
                $query->orderBy('products.name', 'asc');
                break;
        }

        $products = $query->paginate(12)->withQueryString();

        $categories = Product::query()->select('type')->distinct()->orderBy('type')->pluck('type');

        $bestSellerIds = $this->bestSellers(12)->pluck('id')->all();

        return view('storefront.shop', compact(
            'products',
            'categories',
            'search',
            'category',
            'order',
            'layout',
            'price_min',
            'price_max',
            'bestSellerIds'
        ));
    }

    public function product(Product $product): View
    {
        $details = $this->catalogQuery()->where('products.id', $product->id)->firstOrFail();
        $variants = Product::query()
            ->where('type', $product->type)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        $reviews = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sale_items.product_id', $product->id)
            ->select('sales.customer_name', 'sales.sale_date', 'sales.payment_status')
            ->latest('sales.sale_date')
            ->limit(6)
            ->get()
            ->map(function ($row, int $index) {
                $row->rating = $row->payment_status === 'Paid' ? 5 : 4;
                $row->comment = $index % 2 === 0
                    ? 'Great taste and premium quality.'
                    : 'Reliable product consistency and packaging.';

                return $row;
            });

        $partners = Partner::where('is_active', true)->orderBy('sort_order')->get();

        return view('storefront.product', compact('details', 'variants', 'reviews', 'partners'));
    }

    public function contact(): View
    {
        return view('storefront.contact');
    }

    public function submitContact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:160'],
            'company' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:80'],
            'inquiry_type' => ['nullable', 'string', 'in:General Question,Local Sales (Rwanda),International Distribution/Export,Press & Media'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        // Company, country and inquiry type share the existing columns, so no schema change is needed.
        $details = array_filter([
            ! empty($validated['company']) ? 'Company: '.$validated['company'] : null,
            ! empty($validated['country']) ? 'Country: '.$validated['country'] : null,
        ]);
        if (! empty($validated['inquiry_type'])) {
            $validated['subject'] = $validated['inquiry_type'].(! empty($validated['subject']) ? ' - '.$validated['subject'] : '');
        }
        if ($details) {
            $validated['message'] = implode("\n", $details)."\n\n".$validated['message'];
        }
        unset($validated['company'], $validated['country'], $validated['inquiry_type']);

        $submission = ContactSubmission::create($validated);

        // Send to every active recipient configured for the contact_form event.
        $recipients = EmailRoutingRule::recipientsFor('contact_form');

        if (empty($recipients)) {
            Log::warning('[mail] contact_form: no active routing rules — email not sent', [
                'submission_id' => $submission->id,
                'from'          => $submission->email,
            ]);
        }

        // Sent after the response, so the visitor never waits on the mail server.
        defer(function () use ($recipients, $submission): void {
            foreach ($recipients as $recipient) {
                Log::info('[mail] contact_form: attempting', [
                    'submission_id' => $submission->id,
                    'to'            => $recipient->recipient_email,
                    'subject'       => $submission->subject ?: '(no subject)',
                    'from_visitor'  => $submission->email,
                    'mailer'        => config('mail.default'),
                    'host'          => config('mail.mailers.smtp.host'),
                    'port'          => config('mail.mailers.smtp.port'),
                ]);

                try {
                    Mail::to($recipient->recipient_email, $recipient->recipient_name ?: null)
                        ->send(new ContactFormMail($submission));

                    Log::info('[mail] contact_form: sent OK', [
                        'submission_id' => $submission->id,
                        'to'            => $recipient->recipient_email,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[mail] contact_form: FAILED', [
                        'submission_id' => $submission->id,
                        'to'            => $recipient->recipient_email,
                        'error'         => $e->getMessage(),
                        'exception'     => get_class($e),
                        'host'          => config('mail.mailers.smtp.host'),
                        'port'          => config('mail.mailers.smtp.port'),
                    ]);
                }
            }
        });

        return redirect()
            ->route('storefront.contact')
            ->with('status', 'Thank you. Your inquiry has been sent to Exalto Engineering and Supply Solutions Ltd. We will be in touch shortly.');
    }

    public function cart(): View
    {
        $cart = collect(session('storefront_cart', []));
        $rows = $this->hydrateCartRows($cart);
        $subtotal = $rows->sum(fn ($row) => $row['line_total']);

        $partners = Partner::where('is_active', true)->orderBy('sort_order')->get();

        return view('storefront.cart', [
            'rows' => $rows,
            'subtotal' => $subtotal,
            'partners' => $partners,
        ]);
    }

    public function addToCart(Request $request, Product $product): RedirectResponse
    {
        $qty = max((int) $request->integer('quantity', 1), 1);
        $cart = collect(session('storefront_cart', []));
        $current = (int) $cart->get((string) $product->id, 0);
        $cart->put((string) $product->id, $current + $qty);

        session(['storefront_cart' => $cart->all()]);

        return back()->with('status', 'Product added to cart.');
    }

    public function updateCart(Request $request, Product $product): RedirectResponse
    {
        $qty = (int) $request->integer('quantity', 1);
        $cart = collect(session('storefront_cart', []));

        if ($qty <= 0) {
            $cart->forget((string) $product->id);
        } else {
            $cart->put((string) $product->id, $qty);
        }

        session(['storefront_cart' => $cart->all()]);

        return back()->with('status', 'Cart updated.');
    }

    public function wishlist(): View
    {
        $wishlist = collect(session('storefront_wishlist', []))->unique()->values();
        $items = $this->catalogQuery()->whereIn('products.id', $wishlist)->get();

        return view('storefront.wishlist', compact('items'));
    }

    public function addToWishlist(Product $product): RedirectResponse
    {
        $wishlist = collect(session('storefront_wishlist', []));
        $wishlist->push($product->id);
        session(['storefront_wishlist' => $wishlist->unique()->values()->all()]);

        return back()->with('status', 'Added to wishlist.');
    }

    public function checkout(): View
    {
        $cart = collect(session('storefront_cart', []));
        $rows = $this->hydrateCartRows($cart);
        $subtotal = $rows->sum(fn ($row) => $row['line_total']);

        $partners = Partner::where('is_active', true)->orderBy('sort_order')->get();

        return view('storefront.checkout', [
            'rows' => $rows,
            'subtotal' => $subtotal,
            'partners' => $partners,
        ]);
    }

    public function placeOrder(
        Request $request,
        SaleWorkflowService $workflow,
        StorefrontCheckoutService $checkout
    ): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120'],
            'phone' => ['required', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $cart = collect(session('storefront_cart', []));
        $rows = $this->hydrateCartRows($cart);
        if ($rows->isEmpty()) {
            return redirect()->route('storefront.cart')->with('status', 'Your cart is empty.');
        }

        try {
            $items = $checkout->allocateItems($rows);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()])->withInput();
        }

        if ($items === []) {
            return back()->withErrors(['checkout' => 'No line items could be allocated.'])->withInput();
        }

        $invoicePayload = trim($validated['address'] . (isset($validated['notes']) && $validated['notes'] !== '' ? ' | ' . $validated['notes'] : ''));

        try {
            $sale = $workflow->create([
                'customer_name' => $validated['name'],
                'customer_Phone' => $validated['phone'],
                'customer_email' => $validated['email'],
                'delivery_address' => Str::limit($invoicePayload, 255),
                'barcode' => $validated['email'],
                'invoice_number' => Str::limit($invoicePayload, 255),
                'sale_date' => now()->toDateString(),
                'payment_status' => 'Pending',
                'delivery_status' => 'Pending',
                'sales_channel' => 'Online Store',
                'items' => $items,
            ]);
        } catch (\Throwable $e) {
            Log::error('Storefront checkout failed', [
                'message' => $e->getMessage(),
                'customer' => $validated['email'] ?? null,
            ]);

            return back()->withErrors([
                'checkout' => 'We could not save your order. Please try again or contact us. (' . $e->getMessage() . ')',
            ])->withInput();
        }

        $lineSummary = collect($rows)->map(fn (array $row) => ($row['product']->name ?? 'Product') . ' × ' . $row['quantity'])->implode(', ');

        ContactSubmission::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'subject' => 'Website order ' . $sale->sales_id,
            'message' => "New storefront order.\nReference: {$sale->sales_id}\nItems: {$lineSummary}\nDelivery: {$invoicePayload}\nTotal: RWF " . number_format((float) $sale->total_revenue, 2),
        ]);

        // Both emails go out after the response, so checkout never waits on the mail server.
        defer(function () use ($sale, $rows): void {
            // ── Order confirmation email → customer ───────────────────────────────
            try {
                Mail::to($sale->customer_email, $sale->customer_name)
                    ->send(new OrderConfirmationMail($sale, $rows));

                Log::info('[mail] order_confirmation: sent', [
                    'order'    => $sale->sales_id,
                    'to'       => $sale->customer_email,
                    'mailer'   => config('mail.default'),
                ]);
            } catch (\Throwable $e) {
                Log::error('[mail] order_confirmation: FAILED', [
                    'order'   => $sale->sales_id,
                    'to'      => $sale->customer_email,
                    'error'   => $e->getMessage(),
                ]);
            }

            // ── Order notification email → store team ────────────────────────────
            $notifyRecipients = EmailRoutingRule::recipientsFor('order_placed');

            if (empty($notifyRecipients)) {
                Log::warning('[mail] order_notification: no active routing rules for order_placed');
            }

            foreach ($notifyRecipients as $recipient) {
                Log::info('[mail] order_notification: attempting', [
                    'order'  => $sale->sales_id,
                    'to'     => $recipient->recipient_email,
                    'mailer' => config('mail.default'),
                ]);
                try {
                    Mail::to($recipient->recipient_email, $recipient->recipient_name ?? null)
                        ->send(new OrderNotificationMail($sale, $rows));

                    Log::info('[mail] order_notification: sent', [
                        'order' => $sale->sales_id,
                        'to'    => $recipient->recipient_email,
                    ]);
                } catch (\Throwable $e) {
                    Log::error('[mail] order_notification: FAILED', [
                        'order' => $sale->sales_id,
                        'to'    => $recipient->recipient_email,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        session()->forget('storefront_cart');
        session(['storefront_last_order_id' => $sale->id]);

        return redirect()->route('storefront.checkout.thank-you')->with(
            'status',
            'Order placed successfully. Reference: ' . $sale->sales_id . '. Our team will confirm delivery details shortly.'
        );
    }

    public function checkoutThankYou(): View|RedirectResponse
    {
        $saleId = session('storefront_last_order_id');
        if (!$saleId) {
            return redirect()->route('storefront.home');
        }

        $sale = Sale::query()->with(['items.product'])->find($saleId);
        if (!$sale) {
            return redirect()->route('storefront.home');
        }

        return view('storefront.checkout-thank-you', compact('sale'));
    }

    private function catalogQuery(): Builder
    {
        // Only stock that may actually be sold: released and in date.
        $productionSub = Production::query()
            ->sellable()
            ->select('product_id', DB::raw('SUM(quantity_remaining) as finished_goods'))
            ->groupBy('product_id');

        $salesSub = SaleItem::query()
            ->select('product_id', DB::raw('SUM(quantity_sold) as sold_units'))
            ->groupBy('product_id');

        return Product::query()
            ->leftJoinSub($productionSub, 'production_totals', fn ($join) => $join->on('products.id', '=', 'production_totals.product_id'))
            ->leftJoinSub($salesSub, 'sales_totals', fn ($join) => $join->on('products.id', '=', 'sales_totals.product_id'))
            ->select('products.*')
            ->selectRaw('COALESCE(production_totals.finished_goods, 0) as finished_goods')
            ->selectRaw('COALESCE(sales_totals.sold_units, 0) as sold_units')
            ->selectRaw('COALESCE(production_totals.finished_goods, 0) as stock_on_hand')
            ->selectRaw('
                CASE
                    WHEN COALESCE(production_totals.finished_goods, 0) < 0 THEN 0
                    ELSE COALESCE(production_totals.finished_goods, 0)
                END as sellable_qty
            ');
    }

    private function storefrontGalleryAlbumsQuery(): Builder
    {
        return StorefrontGalleryAlbum::query()
            ->where('is_active', true)
            ->whereHas('photos', fn (Builder $query) => $query->where('is_active', true))
            ->withCount(['photos as photos_count' => fn (Builder $query) => $query->where('is_active', true)])
            ->with(['photos' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(1),
            ])
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    private function homeGalleryAlbums(): Collection
    {
        $albums = $this->storefrontGalleryAlbumsQuery()->limit(6)->get();

        if ($albums->isNotEmpty()) {
            return $albums;
        }

        return $this->fallbackGalleryAlbums();
    }

    private function fallbackGalleryAlbums(): Collection
    {
        return $this->fallbackGalleryPhotos()->map(function (StorefrontGalleryPhoto $photo, int $index) {
            $album = new StorefrontGalleryAlbum([
                'title' => $photo->title,
                'is_active' => true,
                'sort_order' => $index,
            ]);
            $album->setRelation('photos', collect([$photo]));
            $album->setAttribute('photos_count', 1);

            return $album;
        });
    }

    private function fallbackGalleryPhotos(): Collection
    {
        return collect([
            [
                'title' => 'Our team behind every bottle',
                'image_path' => 'images/storefront/team.png',
            ],
            [
                'title' => 'Recognition for quality and impact',
                'image_path' => 'images/storefront/award.png',
            ],
            [
                'title' => 'Sharing our story with partners',
                'image_path' => 'images/storefront/pitching.png',
            ],
            [
                'title' => 'From production to presentation',
                'image_path' => 'images/storefront/about-team.png',
            ],
            [
                'title' => 'Events and community engagement',
                'image_path' => 'images/storefront/about-pitching.png',
            ],
        ])->map(function (array $item, int $index) {
            return new StorefrontGalleryPhoto([
                'title' => $item['title'],
                'image_path' => $item['image_path'],
                'is_active' => true,
                'sort_order' => $index,
            ]);
        });
    }

    private function bestSellerProducts(int $limit): Collection
    {
        $orderedIds = SaleItem::query()
            ->selectRaw('product_id, SUM(quantity_sold) as units_sold')
            ->groupBy('product_id')
            ->orderByDesc('units_sold')
            ->limit($limit)
            ->pluck('product_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($orderedIds === []) {
            return collect();
        }

        $byId = $this->catalogQuery()
            ->whereIn('products.id', $orderedIds)
            ->get()
            ->keyBy('id');

        return collect($orderedIds)
            ->map(fn (int $id) => $byId->get($id))
            ->filter()
            ->values();
    }

    /** Ranked over every sale ever made, so cached briefly rather than recomputed per page. */
    private function bestSellers(int $limit): Collection
    {
        return Cache::remember('storefront_best_sellers_'.$limit, 600, fn () => SaleItem::query()
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->selectRaw('products.id, products.name, products.barcode, products.image_path, products.price, SUM(sale_items.quantity_sold) as units_sold')
            ->groupBy('products.id', 'products.name', 'products.barcode', 'products.image_path', 'products.price')
            ->orderByDesc('units_sold')
            ->limit($limit)
            ->get());
    }

    private function hydrateCartRows(Collection $cart): Collection
    {
        if ($cart->isEmpty()) {
            return collect();
        }

        $products = $this->catalogQuery()
            ->whereIn('products.id', $cart->keys()->map(fn ($id) => (int) $id))
            ->get()
            ->keyBy('id');

        return $cart->map(function ($quantity, $productId) use ($products) {
            $product = $products->get((int) $productId);
            if (!$product) {
                return null;
            }

            $price = max((float) $product->price, 0);

            return [
                'product' => $product,
                'quantity' => (int) $quantity,
                'unit_price' => $price,
                'line_total' => $price * (int) $quantity,
            ];
        })->filter()->values();
    }
}
