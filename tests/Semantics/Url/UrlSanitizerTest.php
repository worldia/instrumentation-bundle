<?php

declare(strict_types=1);

/*
 * This file is part of the worldia/instrumentation-bundle package.
 * (c) Worldia <developers@worldia.com>
 */

namespace Tests\Instrumentation\Semantics\Url;

use Instrumentation\Semantics\Url\UrlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlSanitizerTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function urls(): iterable
    {
        yield 'nothing to redact' => ['https://api.example.com/search?q=kyoto&page=2#top', 'https://api.example.com/search?q=kyoto&page=2#top'];
        yield 'user and password' => ['https://login:s3cret@api.example.com/v1', 'https://REDACTED:REDACTED@api.example.com/v1'];
        yield 'a raw @ in the password' => ['https://user:p@ssw0rd@api.example.com/v1?q=a@b', 'https://REDACTED:REDACTED@api.example.com/v1?q=a@b'];
        yield 'a scheme-relative URL' => ['//user:s3cret@api.example.com/v1', '//REDACTED:REDACTED@api.example.com/v1'];
        yield 'array-style names' => ['https://api.example.com/?api_key[]=a&token%5B0%5D=b&q=1', 'https://api.example.com/?api_key[]=REDACTED&token%5B0%5D=REDACTED&q=1'];
        yield 'password only' => ['https://:s3cret@api.example.com/v1', 'https://REDACTED:REDACTED@api.example.com/v1'];
        yield 'an @ in the query is not userinfo' => ['https://api.example.com/v1?email=a%40b.com&to=x@y.com', 'https://api.example.com/v1?email=a%40b.com&to=x@y.com'];
        yield 'a key in the query' => ['https://pixabay.com/api/?key=12345-abc&q=kyoto+temple', 'https://pixabay.com/api/?key=REDACTED&q=kyoto+temple'];
        yield 'names match case-insensitively' => ['https://api.example.com/?API_KEY=abc&Token=def', 'https://api.example.com/?API_KEY=REDACTED&Token=REDACTED'];
        yield 'a repeated parameter is redacted every time' => ['https://api.example.com/?key=a&q=x&key=b', 'https://api.example.com/?key=REDACTED&q=x&key=REDACTED'];
        yield 'an encoded name' => ['https://api.example.com/?api%5Fkey=abc', 'https://api.example.com/?api%5Fkey=REDACTED'];
        yield 'the fragment is kept' => ['https://api.example.com/?sig=abc#frag', 'https://api.example.com/?sig=REDACTED#frag'];
        yield 'a valueless parameter is kept' => ['https://api.example.com/?key&q=1', 'https://api.example.com/?key&q=1'];
        yield 'both at once' => ['http://u:p@host:8080/p?X-Goog-Signature=zz&a=1', 'http://REDACTED:REDACTED@host:8080/p?X-Goog-Signature=REDACTED&a=1'];
        yield 'a relative URL' => ['/some/url?token=abc', '/some/url?token=REDACTED'];
        yield 'not a URL' => ['', ''];
    }

    #[DataProvider('urls')]
    public function testItRedactsCredentials(string $url, string $expected): void
    {
        $this->assertSame($expected, (new UrlSanitizer())->sanitize($url));
    }

    public function testItRedactsTheConfiguredParametersOnly(): void
    {
        $sanitizer = new UrlSanitizer(['appid']);

        $this->assertSame('https://api.example.com/?appid=REDACTED&key=abc', $sanitizer->sanitize('https://api.example.com/?appid=123&key=abc'));
    }

    public function testItRedactsAUrlsOwnExtraParameters(): void
    {
        $sanitizer = new UrlSanitizer();

        $this->assertSame('https://api.example.com/?session=REDACTED&key=REDACTED', $sanitizer->sanitize('https://api.example.com/?session=abc&key=def', ['Session']));
        $this->assertSame('https://api.example.com/?session=abc', $sanitizer->sanitize('https://api.example.com/?session=abc'), 'the extra parameters are this URL\'s only');
    }

    public function testItRedactsAQueryString(): void
    {
        $this->assertSame('code=REDACTED&state=1', (new UrlSanitizer(['code']))->sanitizeQuery('code=abc&state=1'));
        $this->assertSame('', (new UrlSanitizer())->sanitizeQuery(''));
    }
}
