# Synology DSM custom DDNS php module for CloudFlare
A custom php script for CloudFlare DDNS on Synology DSM 6.0+
It has developed based by a origianl script from a few DDNS modules on DSM system in `/usr/bin/syno/ddns/[ddns module name].php`.

## 왜 만들었나?
Synology NAS에 cloudflare ddns를 추가하려고 만들었다.

## 지금은...
Router DDNS 주소를 cloudflare에서 CNAME으로 연결하는 방식으로 그냥 바꿨다.
커스텀 스크립트 넣고 DSM 업데이트 하면 버그 생기고 불편하게 동작하기 때문.

