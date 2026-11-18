<?php

namespace App\Services\Elasticsearch;

class ProductIndexDefinition
{
    public static function body(): array
    {
        return [
            'settings' => [
                'number_of_shards' => 1,
                'number_of_replicas' => 0,
                'analysis' => [
                    'filter' => [
                        'product_synonyms' => [
                            'type' => 'synonym_graph',
                            'synonyms' => [
                                // Pet collars, leashes, and harnesses
                                'dog collar, pet collar, collar',
                                'puppy collar, dog collar, pet collar',
                                'training collar, dog training collar',
                                'id tag, pet tag, dog tag, identification tag',
                                'engraved tag, personalized tag, custom tag',
                                'dog leash, leash',
                                'fabric leash, dog leash, leash',
                                'regular leash, standard leash',
                                'traffic leash, short leash, control leash',
                                'euro leash, european leash, multi-function leash',
                                'dog harness, harness, pet harness',
                                'harness set, harness and leash set, matching set',
                                'dog hook, doghook',

                                // Pet clothing and apparel
                                't shirt, tee, tshirt, tee shirt, shirt',
                                'dog shirt, pet shirt',
                                'hoodie, hoody, hooded sweatshirt, sweatshirt',
                                'sweater, jumper, pullover',
                                'coat, jacket, dog coat, dog jacket',
                                'raincoat, waterproof jacket',
                                'pajamas, pyjamas, pjs',
                                'boots, shoes, dog boots, dog shoes',
                                'hat, cap',
                                'bandana, neckerchief',
                                'bow, bowtie, bow tie',
                                'costume, outfit, clothing, apparel',

                                // Pet food
                                'dog food, food for dogs',
                                'cat food, food for cats',
                                'dry food, kibble',
                                'wet food, canned food',
                                'raw food, freeze dried food, freeze-dried food',
                                'food topper, meal topper, topper, mixer',
                                'puppy food, food for puppies',
                                'adult dog food, dog food',
                                'adult cat food, cat food',
                                'fish food, aquarium food',
                                'rabbit food, rabbit feed',
                                'bird food, bird feed',
                                'feed, food',

                                // Treats and chews
                                'dog treat, dog treats, treat, treats',
                                'cat treat, cat treats',
                                'soft treat, chewy treat, soft chewy treat',
                                'training treat, reward treat',
                                'dental treat, dental chew',
                                'crunchy treat, biscuit, cookie',
                                'chew, dog chew, chew treat',
                                'bully stick, bully sticks',
                                'bone, bones',
                                'long lasting chew, long-lasting chew',
                                'snack, treat',

                                // Toys
                                'dog toy, dog toys',
                                'cat toy, cat toys',
                                'chew toy, chewing toy',
                                'plush toy, soft toy, stuffed toy',
                                'squeaky toy, squeaker toy',
                                'rope toy, tug toy, rope and tug toy',
                                'fetch toy, ball toy',
                                'interactive toy, puzzle toy, enrichment toy',
                                'electronic toy, automatic toy',
                                'teaser, wand, cat wand, teaser wand',
                                'catnip toy, catnip toys',
                                'mouse toy, mice toy',

                                // Beds, crates, carriers, and travel
                                'pet bed, dog bed, cat bed',
                                'orthopedic bed, orthopaedic bed, support bed',
                                'pillow bed, cushion bed',
                                'covered bed, cave bed, hideout bed',
                                'blanket, throw, pet blanket',
                                'crate, kennel, dog crate, dog kennel',
                                'crate mat, kennel mat',
                                'crate cover, kennel cover',
                                'carrier, pet carrier, dog carrier, cat carrier',
                                'backpack carrier, pet backpack',
                                'travel bag, pet travel bag',
                                'stroller, pram, pet stroller',
                                'bicycle basket, bike basket',
                                'ramp, steps, stairs',
                                'car seat, booster seat',
                                'car accessory, travel accessory',

                                // Bowls, feeders, mats, and water
                                'food bowl, pet bowl, dog bowl, cat bowl',
                                'elevated bowl, raised bowl',
                                'slow feeder, slow feed bowl',
                                'feeder, food dispenser',
                                'water dispenser, waterer',
                                'travel bowl, collapsible bowl',
                                'placemat, place mat, feeding mat',
                                'lick mat, licking mat',
                                'snuffle mat, foraging mat',

                                // Grooming, cleaning, and waste
                                'shampoo, pet shampoo, dog shampoo',
                                'conditioner, pet conditioner',
                                'shampoo and conditioner, grooming wash',
                                'brush, comb, grooming brush',
                                'clipper, trimmer, hair clipper',
                                'nail clipper, nail trimmer',
                                'flea and tick, flea tick',
                                'flea collar, tick collar',
                                'ear care, ear cleaner',
                                'eye care, eye cleaner',
                                'stain remover, odour remover, odor remover',
                                'deodorizer, deodoriser',
                                'pet hair remover, lint remover',
                                'poop bag, waste bag, dog bag',
                                'scooper, poop scooper, waste scooper',
                                'pee pad, potty pad, training pad',
                                'dog diaper, pet diaper',
                                'litter box, litter tray',
                                'litter mat, cat litter mat',

                                // Health, supplements, and care
                                'vitamin, vitamins',
                                'supplement, supplements',
                                'health supplement, pet supplement',
                                'topical supplement, skin supplement',
                                'dental care, oral care',
                                'first aid, recovery care',
                                'hairball remedy, hairball treatment',
                                'dewormer, wormer',
                                'milk replacer, nursing kit',
                                'bark control, anti bark, no bark',
                                'gps tracker, pet tracker, dog tracker',
                                'health tracker, activity tracker',

                                // Cat-specific products
                                'cat litter, litter',
                                'catnip, cat grass',
                                'scratching post, scratcher',
                                'cat tree, cat condo, cat tower',
                                'window perch, cat perch',
                                'tunnel, cat tunnel',
                                'tent, cat tent',
                                'sack, cat sack',

                                // Small pets, fish, birds, and reptiles
                                'rabbit, bunny',
                                'guinea pig, cavy',
                                'hamster, gerbil',
                                'ferret, ferrets',
                                'cage, habitat, enclosure',
                                'tank, aquarium, fish tank',
                                'terrarium, reptile tank',
                                'stand, tank stand',
                                'perch, bird perch',
                                'quail egg, quail eggs',

                                // Pet gifts, home, and merchandise
                                'wall art, art print, poster, print',
                                'printable wall art, digital print, instant download',
                                'pet portrait, dog portrait, cat portrait',
                                'custom pet portrait, personalized pet portrait',
                                'memorial gift, remembrance gift',
                                'pet memorial, dog memorial, cat memorial',
                                'mug, cup',
                                'tumbler, bottle',
                                'keychain, key ring',
                                'necklace, pendant',
                                'pillow, cushion',
                                'doormat, door mat, rug',
                                'gift card, e-gift card, digital gift card',
                                'pet lover gift, dog lover gift, cat lover gift',
                                'dog mom gift, dog dad gift',

                                // Digital products
                                'digital download, instant download, downloadable',
                                'printable, digital print, printable art',
                                'printable wall art, digital wall art',
                                'digital poster, printable poster',
                                'pet portrait, digital pet portrait',
                                'digital greeting card, e-card, greeting card',
                                'adoption certificate, pet certificate',
                                'training ebook, e-book, digital book',
                                'training course, video course, online course',
                                'digital tag, qr tag, qr code tag',

                                // Sizes
                                'xs, extra small, x small',
                                's, small',
                                'm, medium',
                                'l, large',
                                'xl, extra large, x large, xlarge',
                                'xxl, 2xl, double extra large',
                                'xxxl, 3xl, triple extra large',
                                '4xl, xxxxlarge',
                                '5xl, xxxxxlarge',
                                'one size, single size',

                                // Colors and materials
                                'gray, grey',
                                'light blue, sky blue',
                                'navy, dark blue',
                                'royal blue, bright blue',
                                'forest green, dark green',
                                'sage, sage green',
                                'hot pink, bright pink',
                                'red, cherry red',
                                'tan, beige, sand',
                                'graphite, charcoal, dark gray',
                                'heather gray, sport grey, athletic heather',
                                'brass, gold',
                                'silver, metal, metallic',
                                'vegan leather, faux leather',
                            ],
                        ],
                        'english_stop' => [
                            'type' => 'stop',
                            'stopwords' => '_english_',
                        ],
                        'english_stemmer' => [
                            'type' => 'stemmer',
                            'language' => 'light_english',
                        ],
                        'autocomplete_filter' => [
                            'type' => 'edge_ngram',
                            'min_gram' => 2,
                            'max_gram' => 20,
                        ],
                        'joined_words_filter' => [
                            'type' => 'shingle',
                            'min_shingle_size' => 2,
                            'max_shingle_size' => 2,
                            'output_unigrams' => false,
                            'output_unigrams_if_no_shingles' => true,
                            'token_separator' => '',
                        ],
                    ],
                    'char_filter' => [
                        'remove_apostrophes' => [
                            'type' => 'pattern_replace',
                            'pattern' => "['’]",
                            'replacement' => '',
                        ],
                    ],
                    'analyzer' => [
                        'product_index_analyzer' => [
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'asciifolding', 'english_stop', 'english_stemmer'],
                            'char_filter' => ['remove_apostrophes'],
                        ],
                        'product_search_analyzer' => [
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'asciifolding', 'product_synonyms', 'english_stop', 'english_stemmer'],
                            'char_filter' => ['remove_apostrophes'],
                        ],
                        'autocomplete_index_analyzer' => [
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'asciifolding', 'autocomplete_filter'],
                            'char_filter' => ['remove_apostrophes'],
                        ],
                        'autocomplete_search_analyzer' => [
                            'tokenizer' => 'standard',
                            'filter' => ['lowercase', 'asciifolding'],
                            'char_filter' => ['remove_apostrophes'],
                        ],
                        'joined_words_analyzer' => [
                            'tokenizer' => 'standard',
                            'filter' => [
                                'lowercase',
                                'asciifolding',
                                'joined_words_filter',
                            ],
                            'char_filter' => ['remove_apostrophes'],
                        ],
                    ],
                    'normalizer' => [
                        'lowercase_ascii' => [
                            'type' => 'custom',
                            'filter' => ['lowercase', 'asciifolding'],
                        ],
                    ],
                ],
            ],
            'mappings' => [
                'dynamic' => 'strict',
                'properties' => [
                    'id' => ['type' => 'long'],
                    'uuid' => ['type' => 'keyword'],
                    'slug' => ['type' => 'keyword'],
                    'type' => ['type' => 'integer'],
                    'status' => ['type' => 'integer'],
                    'title' => self::searchableText(256, true),
                    'description' => self::searchableText(),
                    'origin' => ['type' => 'keyword', 'normalizer' => 'lowercase_ascii'],
                    'source_id' => ['type' => 'keyword'],
                    'media_url' => ['type' => 'keyword', 'index' => false],
                    'category' => [
                        'properties' => [
                            'id' => ['type' => 'long'],
                            'uuid' => ['type' => 'keyword'],
                            'name' => self::searchableText(256),
                            'slug' => ['type' => 'keyword'],
                            'path' => self::searchableKeywordText(),
                        ],
                    ],
                    'category_names' => self::searchableKeywordText(),
                    'tags' => [
                        'properties' => [
                            'id' => ['type' => 'long'],
                            'uuid' => ['type' => 'keyword'],
                            'name' => self::searchableText(256),
                            'slug' => ['type' => 'keyword'],
                        ],
                    ],
                    'tag_names' => self::searchableKeywordText(),
                    'store' => [
                        'properties' => [
                            'id' => ['type' => 'long'],
                            'uuid' => ['type' => 'keyword'],
                            'name' => self::searchableText(256),
                            'slug' => ['type' => 'keyword'],
                            'country_id' => ['type' => 'long'],
                            'is_verified' => ['type' => 'boolean'],
                        ],
                    ],
                    'store_name' => self::searchableText(256, true),
                    'sku' => ['type' => 'keyword', 'normalizer' => 'lowercase_ascii'],
                    'variant_skus' => ['type' => 'keyword', 'normalizer' => 'lowercase_ascii'],
                    'attribute_names' => self::searchableKeywordText(),
                    'attribute_options' => self::searchableKeywordText(),
                    'facets' => [
                        'properties' => [
                            'id' => ['type' => 'long'],
                            'name' => self::searchableText(256),
                            'category_facet_id' => ['type' => 'long'],
                        ],
                    ],
                    'price' => [
                        'properties' => [
                            'domestic' => ['type' => 'integer'],
                            'global' => ['type' => 'integer'],
                        ],
                    ],
                    'stock_quantity' => ['type' => 'integer'],
                    'allow_overselling' => ['type' => 'boolean'],
                    'in_stock' => ['type' => 'boolean'],
                    'has_image' => ['type' => 'boolean'],
                    'rating_avg' => ['type' => 'float'],
                    'reviews_count' => ['type' => 'integer'],
                    'discount_value' => ['type' => 'integer'],
                    'suggest_title' => self::completionSuggestField(),
                    'suggest_store' => self::completionSuggestField(),
                    'suggest_tag' => self::completionSuggestField(),
                    'created_at' => ['type' => 'date'],
                    'updated_at' => ['type' => 'date'],
                ],
            ],
        ];
    }

    /**
     * Completion suggester field used for typeahead.
     * store_id context lets suggest() restrict results to country-eligible stores.
     */
    private static function completionSuggestField(): array
    {
        return [
            'type' => 'completion',
            'analyzer' => 'autocomplete_search_analyzer',
            'search_analyzer' => 'autocomplete_search_analyzer',
            'preserve_separators' => true,
            'contexts' => [
                [
                    'name' => 'store_id',
                    'type' => 'category',
                ],
            ],
        ];
    }

    private static function searchableText(?int $ignoreAbove = null, bool $withJoinedWords = false): array
    {
        $fields = [
            'keyword' => [
                'type' => 'keyword',
                'normalizer' => 'lowercase_ascii',
            ],
            'autocomplete' => [
                'type' => 'text',
                'analyzer' => 'autocomplete_index_analyzer',
                'search_analyzer' => 'autocomplete_search_analyzer',
            ],
        ];

        if ($ignoreAbove !== null) {
            $fields['keyword']['ignore_above'] = $ignoreAbove;
        }

        if ($withJoinedWords) {
            $fields['joined'] = [
                'type' => 'text',
                'analyzer' => 'joined_words_analyzer',
                'search_analyzer' => 'joined_words_analyzer',
            ];
        }

        return [
            'type' => 'text',
            'analyzer' => 'product_index_analyzer',
            'search_analyzer' => 'product_search_analyzer',
            'fields' => $fields,
        ];
    }

    private static function searchableKeywordText(): array
    {
        return [
            'type' => 'text',
            'analyzer' => 'product_index_analyzer',
            'search_analyzer' => 'product_search_analyzer',
            'fields' => [
                'keyword' => [
                    'type' => 'keyword',
                    'normalizer' => 'lowercase_ascii',
                    'ignore_above' => 256,
                ],
                'autocomplete' => [
                    'type' => 'text',
                    'analyzer' => 'autocomplete_index_analyzer',
                    'search_analyzer' => 'autocomplete_search_analyzer',
                ],
            ],
        ];
    }
}
