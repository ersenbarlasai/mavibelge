/**
 * Katalog aktarımı — admin orkestrasyonu (Faz 6B4).
 *
 * KARAR MANTIĞI İÇERMEZ: her karar (yetki, sabitler, aşama sırası, digest, onay ifadesi, checkpoint, çakışma) sunucuda
 * verilir. Bu dosya yalnız (1) nonce'lı, kapalı anahtar kümeli POST'ları sırayla gönderir, (2) sunucunun döndürdüğü
 * `checkpoint` ile bir sonraki tek-batch isteğini yapar, (3) çift tıklamada düğmeleri kilitler, (4) sunucu yanıtını
 * YALNIZ textContent ile gösterir (innerHTML yok). Sayfa kapanırsa sunucu run'ı `paused` bırakır; yenilenince "Devam et"
 * düğmesi sunucu durumundan (data-checkpoint) sürdürür.
 */
( function () {
	'use strict';

	var cfg = window.mbImportApply;
	if ( ! cfg ) {
		return;
	}

	var MESSAGES = {
		method_not_post: 'İstek yöntemi POST olmalı.',
		not_https: 'Yönetim isteği HTTPS üzerinden olmalı.',
		not_logged_in: 'Oturum gerekli.',
		missing_capability: 'Yetki eksik (manage_options ve mb_manage_tariff_period gerekir).',
		apply_disabled: 'Apply kapalı: MAVIBELGE_IMPORT_APPLY_ENABLED.',
		admin_apply_disabled: 'Admin apply kapalı: MAVIBELGE_IMPORT_ADMIN_APPLY_ENABLED.',
		environment_not_allowed: 'Ortam türü staging (veya üretim için production) olmalı.',
		production_disabled: 'Üretim apply kapalı.',
		production_host_mismatch: 'Üretim host sabiti bu host ile eşleşmiyor.',
		invalid_nonce: 'Güvenlik anahtarı geçersiz; sayfayı yenileyin.',
		invalid_request: 'Geçersiz istek.',
		unexpected_request_key: 'Geçersiz istek şekli.',
		invalid_stage: 'Geçersiz aşama.',
		invalid_confirmation: 'Geçersiz onay.',
		prerequisite_not_met: 'Önceki aşama tamamlanmadan bu aşama açılamaz.',
		confirmation_phrase_mismatch: 'Onay ifadesi birebir eşleşmiyor.',
		confirmation_mismatch: 'Plan onaydan sonra değişti; önizlemeyi yenileyin.',
		plan_not_applicable: 'Plan uygulanabilir değil (conflict/blocked/invalid).',
		payload_invalid: 'Yazma yükü doğrulanamadı.',
		unresolved_run_exists: 'Çözülmemiş bir run var; önce onu tamamlayın veya geri alın.',
		locked: 'Başka bir işlem çalışıyor; birkaç saniye sonra tekrar deneyin.',
		stale_request: 'Eski veya tekrarlanan istek; sayfayı yenileyin.',
		run_not_found: 'Run bulunamadı.',
		run_not_resumable: 'Run bu durumda devam ettirilemez.',
		run_not_rollbackable: 'Run bu durumda geri alınamaz.',
		manifest_changed: 'Manifest çalışma sırasında değişti; run durduruldu.',
		map_changed: 'Görsel eşleme çalışma sırasında değişti; run durduruldu.',
		infrastructure_unavailable: 'Altyapı hazır değil (transaction/audit).',
		drift_detected: 'Kullanıcı değişikliği (drift) bulundu; hiçbir kayıt geri alınmadı.',
		finalization_failed: 'Durum kaydı tamamlanamadı; run durumunu listeden kontrol edin.',
		toctou_drift: 'Hedef planlamadan sonra değişti; batch yazılmadı.',
		run_start_failed: 'Run başlatılamadı.',
		run_create_failed: 'Run oluşturulamadı.',
		publish_staging_only: 'Yayınlama yalnız staging ortamında yapılabilir.',
		plan_not_available: 'Sayfa planı okunamadı.',
		transaction_begin_failed: 'Transaction başlatılamadı; hiçbir sayfa yayınlanmadı.',
		write_failed: 'Yayın yazılamadı; batch geri alındı.',
		readback_mismatch: 'Yayın sonrası doğrulama tutmadı; batch geri alındı.',
		audit_failed: 'Audit yazılamadı; batch geri alındı.',
		commit_failed: 'Commit başarısız; batch geri alındı.',
		transaction_rollback_failed: 'Veritabanı geri alma başarısız; yeni oluşturulan dosyalar bilerek SİLİNMEDİ. Sistem yöneticisi incelemeli.',
		side_effect_scope_failed: 'Dosya yan etki kapsamı açılamadı; batch geri alındı.',
		side_effect_cleanup_failed: 'Batch geri alındı ancak yeni oluşturulan logo dosyaları temizlenemedi. Sistem yöneticisi uploads dizinini incelemeli.',
		side_effect_attachment_still_present: 'Batch geri alındı ancak dosya temizliği doğrulanamadı (attachment hâlâ görünüyor); dosya silinmedi.',
		side_effect_file_referenced: 'Batch geri alındı ancak dosya başka bir kayıtça kullanıldığı için silinmedi.',
		side_effect_path_rejected: 'Batch geri alındı ancak dosya yolu güvenlik sınırı dışında olduğu için silinmedi.'
	};

	var busy = false;
	var statusBox = document.getElementById( 'mb-import-status' );

	function message( code ) {
		if ( ! code ) {
			return '';
		}
		return MESSAGES[ code ] ? MESSAGES[ code ] + ' (' + code + ')' : 'İşlem reddedildi (' + code + ').';
	}

	function show( text, kind ) {
		if ( ! statusBox ) {
			return;
		}
		statusBox.style.display = 'block';
		statusBox.className = 'notice inline notice-' + ( kind || 'info' );
		statusBox.firstChild.textContent = text;
	}

	function lock( on ) {
		busy = on;
		var buttons = document.querySelectorAll( '#mb-import-apply-title ~ * button, #mb-import-apply-title ~ * select' );
		Array.prototype.forEach.call( buttons, function ( b ) {
			b.disabled = on;
		} );
	}

	/** Kapalı anahtar kümesi: action + nonce + çağıranın alanları; başka HİÇBİR alan gönderilmez. */
	function post( name, fields ) {
		var body = new URLSearchParams();
		body.append( 'action', cfg.actions[ name ] );
		body.append( cfg.nonceField, cfg.nonces[ name ] );
		Object.keys( fields || {} ).forEach( function ( key ) {
			var value = fields[ key ];
			if ( value !== null && typeof value === 'object' ) {
				Object.keys( value ).forEach( function ( sub ) {
					body.append( key + '[' + sub + ']', String( value[ sub ] ) );
				} );
			} else {
				body.append( key, String( value ) );
			}
		} );
		return window.fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		} ).then( function ( response ) {
			return response.json().catch( function () {
				return { ok: false, error_code: 'invalid_request' };
			} );
		} ).catch( function () {
			return { ok: false, error_code: 'network_error' };
		} );
	}

	function el( id ) {
		return document.getElementById( id );
	}

	function clear( node ) {
		while ( node.firstChild ) {
			node.removeChild( node.firstChild );
		}
	}

	function line( parent, text, tag ) {
		var node = document.createElement( tag || 'p' );
		node.textContent = text;
		parent.appendChild( node );
		return node;
	}

	function progress( data ) {
		var box = el( 'mb-progress' );
		if ( ! box ) {
			return;
		}
		box.style.display = 'block';
		var bar = el( 'mb-progress-bar' );
		bar.max = Math.max( 1, data.total || 1 );
		bar.value = data.committed || 0;
		el( 'mb-progress-text' ).textContent = 'Durum: ' + data.status + ' — ' + ( data.committed || 0 ) + '/' + ( data.total || 0 ) + ' kayıt, checkpoint ' + ( data.checkpoint || 0 ) + ', kalan ' + ( data.remaining || 0 );
	}

	/** Sunucu `paused`/`rollback_paused` dönmeye devam ettikçe bir sonraki tek-batch isteğini gönderir. */
	function advanceLoop( kind, uid, checkpoint ) {
		var action = kind === 'apply' ? 'advance_apply' : 'advance_rollback';
		var pausedStatus = kind === 'apply' ? 'paused' : 'rollback_paused';
		return post( action, { run_uid: uid, expected_checkpoint: checkpoint } ).then( function ( data ) {
			if ( data && typeof data.status === 'string' ) {
				progress( data );
			}
			if ( ! data || ! data.ok ) {
				show( message( data && data.error_code ) + ( data && data.status ? ' — durum: ' + data.status : '' ), 'error' );
				return data;
			}
			if ( data.status === pausedStatus ) {
				return advanceLoop( kind, uid, data.checkpoint );
			}
			show( kind === 'apply' ? 'Apply tamamlandı (' + data.status + ').' : 'Geri alma tamamlandı (' + data.status + ').', 'success' );
			return data;
		} );
	}

	function finish( promise ) {
		return promise.then( function () {
			lock( false );
			window.setTimeout( function () {
				window.location.reload();
			}, 1200 );
		} );
	}

	/* ---------------- sektör görsel eşleme (Ortam Kütüphanesi) ---------------- */
	var frame = null;
	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.classList ) {
			return;
		}
		if ( target.classList.contains( 'mb-pick-image' ) && window.wp && window.wp.media ) {
			event.preventDefault();
			var row = target.closest( 'tr' );
			frame = window.wp.media( { title: 'Sektör görselini seçin', library: { type: 'image' }, multiple: false, button: { text: 'Bu görseli kullan' } } );
			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				row.querySelector( '.mb-image-input' ).value = String( attachment.id );
				row.querySelector( '.mb-image-id' ).textContent = String( attachment.id );
				var preview = row.querySelector( '.mb-image-preview' );
				clear( preview );
				if ( attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url ) {
					var img = document.createElement( 'img' );
					img.src = attachment.sizes.thumbnail.url;
					img.alt = '';
					img.width = 60;
					preview.appendChild( img );
				}
			} );
			frame.open();
		}
	} );

	var saveBtn = el( 'mb-save-image-map' );
	if ( saveBtn ) {
		saveBtn.addEventListener( 'click', function () {
			if ( busy ) {
				return;
			}
			var mappings = {};
			var complete = true;
			Array.prototype.forEach.call( document.querySelectorAll( '#mb-image-map-table tbody tr' ), function ( row ) {
				var value = row.querySelector( '.mb-image-input' ).value;
				if ( ! /^[1-9][0-9]*$/.test( value ) ) {
					complete = false;
				}
				mappings[ row.getAttribute( 'data-slug' ) ] = value;
			} );
			if ( ! complete ) {
				show( 'Bütün sektörler için görsel seçin.', 'warning' );
				return;
			}
			lock( true );
			post( 'save_sector_image_map', { mappings: mappings } ).then( function ( data ) {
				if ( data && data.ok ) {
					show( 'Eşleme doğrulandı ve kaydedildi.', 'success' );
				} else if ( data && Array.isArray( data.error_codes ) && data.error_codes.length ) {
					show( 'Eşleme kaydedilmedi: ' + data.error_codes.join( ', ' ), 'error' );
				} else {
					show( 'Eşleme kaydedilmedi. ' + message( data && data.error_code ), 'error' );
				}
				lock( false );
			} );
		} );
	}

	/* ---------------- aşama önizleme + apply ---------------- */
	var lastPreview = null;
	var previewBtn = el( 'mb-preview-stage' );
	if ( previewBtn ) {
		previewBtn.addEventListener( 'click', function () {
			if ( busy ) {
				return;
			}
			lock( true );
			var stage = el( 'mb-stage' ).value;
			post( 'preview_stage', { stage: stage } ).then( function ( data ) {
				lock( false );
				var box = el( 'mb-preview-result' );
				clear( box );
				box.style.display = 'block';
				el( 'mb-apply-confirm' ).style.display = 'none';
				lastPreview = null;
				if ( ! data || data.ok !== true ) {
					show( message( data && data.error_code ), 'error' );
					return;
				}
				var s = data.summary;
				line( box, 'Aşama: ' + data.stage + ' — toplam ' + s.total + ': create ' + s.operations.create + ', update ' + s.operations.update + ', unchanged ' + s.operations.unchanged + ', conflict ' + s.operations.conflict + ', blocked ' + s.operations.blocked + ', invalid ' + s.operations.invalid + '.' );
				line( box, 'Uygulanabilir: ' + ( data.eligible ? 'EVET' : 'HAYIR' ) + ' — yazılacak ' + data.writes + ', değişmeyen ' + data.noops + '. Önkoşul: ' + ( data.prerequisites.met ? 'karşılandı' : 'KARŞILANMADI' + ( data.prerequisites.requires ? ' (' + data.prerequisites.requires + ' gerekli)' : '' ) ) + '.' );
				line( box, 'Plan özeti: ' + data.plan_digest );
				if ( data.map ) {
					line( box, 'Görsel eşleme: ' + ( data.map.present ? ( data.map.valid ? 'geçerli' : 'geçersiz' ) : 'yok' ) + ' (' + data.map.mapped + '/' + data.map.required + ').' );
				}
				( data.notices || [] ).forEach( function ( notice ) {
					line( box, ( notice.blocking ? '[YAYIN BEKLETİLİR] ' : '[UYARI] ' ) + notice.title + ' — kurum kararı bekliyor: ' + notice.pending.join('; ') );
				} );
				var counts = {};
				data.entries.forEach( function ( entry ) {
					var key = entry.type + ' → ' + entry.decision + ( entry.reason ? ' (' + entry.reason + ')' : '' );
					counts[ key ] = ( counts[ key ] || 0 ) + 1;
				} );
				var list = document.createElement( 'ul' );
				Object.keys( counts ).forEach( function ( key ) {
					line( list, key + ': ' + counts[ key ], 'li' );
				} );
				box.appendChild( list );
				if ( data.eligible && data.prerequisites.met && data.writes > 0 ) {
					lastPreview = data;
					el( 'mb-apply-phrase-hint' ).textContent = 'UYGULA ' + data.stage + ' ' + data.plan_digest.substr( 0, 12 );
					el( 'mb-apply-phrase' ).value = '';
					el( 'mb-apply-confirm' ).style.display = 'block';
				}
			} );
		} );
	}

	var startBtn = el( 'mb-start-apply' );
	if ( startBtn ) {
		startBtn.addEventListener( 'click', function () {
			if ( busy || ! lastPreview ) {
				return;
			}
			lock( true );
			var preview = lastPreview;
			post( 'start_apply', { stage: preview.stage, plan_digest: preview.plan_digest, confirm_phrase: el( 'mb-apply-phrase' ).value } ).then( function ( data ) {
				if ( ! data || ! data.ok ) {
					show( message( data && data.error_code ), 'error' );
					lock( false );
					return;
				}
				if ( data.status === 'noop' ) {
					show( 'Yazılacak kayıt yok.', 'info' );
					lock( false );
					return;
				}
				progress( data );
				finish( advanceLoop( 'apply', data.run_uid, data.checkpoint ) );
			} );
		} );
	}

	/* ---------------- sayfa yayınlama (Faz 12; ayrı, açık onaylı, yalnız staging) ---------------- */
	var lastPublish = null;
	var REASON_TEXT = {
		ready: 'yayına hazır (taslak)',
		published: 'yayında',
		held_pending_decision: 'BEKLETİLİYOR — kurum kararı bekliyor',
		content_not_ready: 'BEKLETİLİYOR — içerik kayıtları (SSS/referans) henüz oluşmadı veya yayınlanmadı',
		not_created: 'henüz oluşturulmadı (önce pages aşaması)',
		not_unchanged: 'içerik manifestten sapmış',
		unexpected_status: 'beklenmeyen durum'
	};

	function publishLoop( digest, expected ) {
		return post( 'publish_pages', { plan_digest: digest, confirm_phrase: el( 'mb-publish-phrase' ).value, expected_remaining: expected } ).then( function ( data ) {
			if ( ! data || ! data.ok ) {
				show( message( data && data.error_code ), 'error' );
				return data;
			}
			show( 'Yayınlanan (bu istek): ' + data.published + ', kalan hazır: ' + data.remaining + '.', data.status === 'completed' ? 'success' : 'info' );
			if ( data.status === 'paused' ) {
				return publishLoop( digest, data.remaining );
			}
			return data;
		} );
	}

	var previewPublishBtn = el( 'mb-preview-publish' );
	if ( previewPublishBtn ) {
		previewPublishBtn.addEventListener( 'click', function () {
			if ( busy ) {
				return;
			}
			lock( true );
			post( 'preview_publish', {} ).then( function ( data ) {
				lock( false );
				var box = el( 'mb-publish-result' );
				clear( box );
				box.style.display = 'block';
				el( 'mb-publish-confirm' ).style.display = 'none';
				lastPublish = null;
				if ( ! data || data.ok !== true ) {
					show( message( data && data.error_code ), 'error' );
					return;
				}
				var s = data.summary;
				line( box, 'Toplam ' + s.total + ' sayfa: hazır ' + s.ready + ', yayında ' + s.published + ', bekletilen (kurum kararı) ' + s.held_pending_decision + ', içerik bekleyen ' + s.content_not_ready + ', oluşturulmamış ' + s.not_created + ', sapmış ' + s.not_unchanged + ', beklenmeyen ' + s.unexpected_status + '.' );
				line( box, 'Yayın planı özeti: ' + data.plan_digest );
				var table = document.createElement( 'table' );
				table.className = 'widefat striped';
				var head = document.createElement( 'tr' );
				[ 'Sayfa', 'Slug', 'Kaynak', 'Durum', 'Yayın', 'Bekleyen kurum kararları' ].forEach( function ( h ) {
					line( head, h, 'th' );
				} );
				table.appendChild( head );
				data.rows.forEach( function ( row ) {
					var tr = document.createElement( 'tr' );
					line( tr, row.title, 'td' );
					line( tr, row.slug, 'td' );
					line( tr, row.source_file, 'td' );
					line( tr, row.status || '—', 'td' );
					line( tr, REASON_TEXT[ row.reason ] || row.reason, 'td' );
					var cell = document.createElement( 'td' );
					row.pending.forEach( function ( p ) {
						line( cell, ( p.blocking ? '[BLOKLAYICI] ' : '' ) + p.label, 'div' );
					} );
					tr.appendChild( cell );
					table.appendChild( tr );
				} );
				box.appendChild( table );
				if ( s.ready > 0 ) {
					lastPublish = data;
					el( 'mb-publish-phrase-hint' ).textContent = data.phrase;
					el( 'mb-publish-phrase' ).value = '';
					el( 'mb-publish-confirm' ).style.display = 'block';
				}
			} );
		} );
	}

	var startPublishBtn = el( 'mb-start-publish' );
	if ( startPublishBtn ) {
		startPublishBtn.addEventListener( 'click', function () {
			if ( busy || ! lastPublish ) {
				return;
			}
			lock( true );
			finish( publishLoop( lastPublish.plan_digest, lastPublish.summary.ready ) );
		} );
	}

	/* ---------------- run listesi: devam et / geri alma ---------------- */
	var pendingRollback = null;
	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( busy || ! target || ! target.classList ) {
			return;
		}
		var row = target.closest ? target.closest( 'tr[data-run-uid]' ) : null;
		if ( ! row ) {
			return;
		}
		var uid = row.getAttribute( 'data-run-uid' );
		if ( target.classList.contains( 'mb-run-continue-apply' ) ) {
			lock( true );
			finish( advanceLoop( 'apply', uid, parseInt( row.getAttribute( 'data-checkpoint' ), 10 ) ) );
		} else if ( target.classList.contains( 'mb-run-continue-rollback' ) ) {
			lock( true );
			finish( advanceLoop( 'rollback', uid, parseInt( row.getAttribute( 'data-rollback-checkpoint' ), 10 ) ) );
		} else if ( target.classList.contains( 'mb-run-preview-rollback' ) ) {
			lock( true );
			post( 'preview_rollback', { run_uid: uid } ).then( function ( data ) {
				lock( false );
				var box = el( 'mb-rollback-result' );
				clear( box );
				box.style.display = 'block';
				el( 'mb-rollback-confirm' ).style.display = 'none';
				pendingRollback = null;
				if ( ! data || ! data.ok ) {
					show( message( data && data.error_code ), 'error' );
					return;
				}
				line( box, 'Geri alınacak bekleyen kayıt: ' + data.items_pending + ' — durum: ' + data.status + '.' );
				line( box, 'Geri alma özeti: ' + data.rollback_digest );
				if ( data.blockers.length ) {
					var ul = document.createElement( 'ul' );
					data.blockers.forEach( function ( b ) {
						line( ul, ( b.source_key || '—' ) + ': ' + b.code, 'li' );
					} );
					box.appendChild( ul );
					show( 'Geri alma engelleri var; hiçbir kayıt geri alınmaz.', 'warning' );
					return;
				}
				pendingRollback = data;
				el( 'mb-rollback-phrase-hint' ).textContent = 'GERI AL ' + data.run_uid.substr( 0, 8 ) + ' ' + data.rollback_digest.substr( 0, 12 );
				el( 'mb-rollback-phrase' ).value = '';
				el( 'mb-rollback-confirm' ).style.display = 'block';
			} );
		}
	} );

	var startRollback = el( 'mb-start-rollback' );
	if ( startRollback ) {
		startRollback.addEventListener( 'click', function () {
			if ( busy || ! pendingRollback ) {
				return;
			}
			lock( true );
			var pending = pendingRollback;
			post( 'start_rollback', { run_uid: pending.run_uid, rollback_digest: pending.rollback_digest, confirm_phrase: el( 'mb-rollback-phrase' ).value } ).then( function ( data ) {
				if ( ! data || ! data.ok ) {
					show( message( data && data.error_code ), 'error' );
					lock( false );
					return;
				}
				progress( data );
				finish( advanceLoop( 'rollback', data.run_uid, data.checkpoint ) );
			} );
		} );
	}
}() );
