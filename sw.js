// 뷰티북 서비스 워커
const CACHE_NAME = 'beautybook-v1.0.0';
const urlsToCache = [
  '/view/',
  '/view/index.php',
  '/view/pages/login.php',
  '/view/pages/register.php',
  '/view/pages/business_list.php',
  '/view/pages/customer_mypage.php',
  '/view/includes/header.php',
  '/view/includes/footer.php',
  '/view/includes/functions.php',
  '/view/assets/js/common.js',
  '/view/manifest.json',
  // 외부 라이브러리
  'https://code.jquery.com/jquery-3.6.0.min.js',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;600;700&display=swap'
];

// 서비스 워커 설치
self.addEventListener('install', event => {
  console.log('Service Worker 설치 중...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('캐시 오픈 완료');
        return cache.addAll(urlsToCache);
      })
      .then(() => {
        console.log('모든 파일 캐시 완료');
        return self.skipWaiting();
      })
  );
});

// 서비스 워커 활성화
self.addEventListener('activate', event => {
  console.log('Service Worker 활성화 중...');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (cacheName !== CACHE_NAME) {
            console.log('이전 캐시 삭제:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('Service Worker 활성화 완료');
      return self.clients.claim();
    })
  );
});

// 네트워크 요청 가로채기
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        // 캐시에서 찾은 경우
        if (response) {
          return response;
        }

        // 네트워크에서 가져오기
        return fetch(event.request).then(response => {
          // 유효하지 않은 응답 확인
          if (!response || response.status !== 200 || response.type !== 'basic') {
            return response;
          }

          // 응답 복제
          const responseToCache = response.clone();

          // 캐시에 저장
          caches.open(CACHE_NAME)
            .then(cache => {
              cache.put(event.request, responseToCache);
            });

          return response;
        }).catch(() => {
          // 오프라인 상태에서 기본 페이지 반환
          if (event.request.destination === 'document') {
            return caches.match('/view/offline.html') || 
                   caches.match('/view/index.php');
          }
          
          // 이미지 요청이 실패한 경우 기본 이미지 반환
          if (event.request.destination === 'image') {
            return new Response(
              '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200"><rect width="200" height="200" fill="#f8f9fa"/><text x="100" y="100" text-anchor="middle" dy=".3em" font-family="Arial" font-size="14" fill="#6c757d">이미지를 불러올 수 없습니다</text></svg>',
              { headers: { 'Content-Type': 'image/svg+xml' } }
            );
          }
        });
      })
  );
});

// 백그라운드 동기화
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    console.log('백그라운드 동기화 실행');
    event.waitUntil(doBackgroundSync());
  }
});

// 푸시 알림 수신
self.addEventListener('push', event => {
  console.log('푸시 알림 수신:', event);
  
  const options = {
    body: event.data ? event.data.text() : '새로운 알림이 있습니다.',
    icon: '/view/assets/icons/icon-192x192.png',
    badge: '/view/assets/icons/badge-72x72.png',
    tag: 'beautybook-notification',
    data: {
      url: '/view/'
    },
    actions: [
      {
        action: 'open',
        title: '확인',
        icon: '/view/assets/icons/action-open.png'
      },
      {
        action: 'close',
        title: '닫기',
        icon: '/view/assets/icons/action-close.png'
      }
    ],
    requireInteraction: true,
    silent: false,
    vibrate: [200, 100, 200],
    timestamp: Date.now()
  };

  event.waitUntil(
    self.registration.showNotification('뷰티북', options)
  );
});

// 알림 클릭 처리
self.addEventListener('notificationclick', event => {
  console.log('알림 클릭:', event);
  
  event.notification.close();
  
  if (event.action === 'open') {
    const urlToOpen = event.notification.data?.url || '/view/';
    
    event.waitUntil(
      clients.matchAll({
        type: 'window',
        includeUncontrolled: true
      }).then(clientList => {
        // 이미 열린 탭이 있는지 확인
        for (const client of clientList) {
          if (client.url === urlToOpen && 'focus' in client) {
            return client.focus();
          }
        }
        
        // 새 탭에서 열기
        if (clients.openWindow) {
          return clients.openWindow(urlToOpen);
        }
      })
    );
  }
});

// 백그라운드 동기화 함수
async function doBackgroundSync() {
  try {
    // 오프라인에서 저장된 데이터 동기화
    const offlineData = await getOfflineData();
    
    if (offlineData && offlineData.length > 0) {
      for (const data of offlineData) {
        await syncData(data);
      }
      
      // 동기화 완료 후 오프라인 데이터 삭제
      await clearOfflineData();
    }
    
    console.log('백그라운드 동기화 완료');
  } catch (error) {
    console.error('백그라운드 동기화 실패:', error);
  }
}

// 오프라인 데이터 가져오기
async function getOfflineData() {
  try {
    const cache = await caches.open('offline-data');
    const keys = await cache.keys();
    const data = [];
    
    for (const key of keys) {
      const response = await cache.match(key);
      if (response) {
        const jsonData = await response.json();
        data.push(jsonData);
      }
    }
    
    return data;
  } catch (error) {
    console.error('오프라인 데이터 가져오기 실패:', error);
    return [];
  }
}

// 데이터 동기화
async function syncData(data) {
  try {
    const response = await fetch(data.url, {
      method: data.method || 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data.payload)
    });
    
    if (!response.ok) {
      throw new Error('동기화 요청 실패');
    }
    
    console.log('데이터 동기화 성공:', data);
  } catch (error) {
    console.error('데이터 동기화 실패:', error);
    // 실패한 경우 다시 오프라인 저장소에 저장
    throw error;
  }
}

// 오프라인 데이터 삭제
async function clearOfflineData() {
  try {
    await caches.delete('offline-data');
    console.log('오프라인 데이터 삭제 완료');
  } catch (error) {
    console.error('오프라인 데이터 삭제 실패:', error);
  }
}

// 메시지 이벤트 처리
self.addEventListener('message', event => {
  console.log('메시지 수신:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }
  
  if (event.data && event.data.type === 'GET_VERSION') {
    event.ports[0].postMessage({
      type: 'VERSION',
      version: CACHE_NAME
    });
  }
});

// 업데이트 확인
self.addEventListener('message', event => {
  if (event.data.action === 'skipWaiting') {
    self.skipWaiting();
  }
});

console.log('뷰티북 Service Worker 로드 완료');