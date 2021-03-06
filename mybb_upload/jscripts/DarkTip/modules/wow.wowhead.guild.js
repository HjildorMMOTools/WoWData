DarkTip.registerModule('wow.wowhead.guild', {
	
	'triggers': {
		'implicit': {
			'match' : /http:\/\/(www\.wowhead\.com|de\.wowhead\.com|es\.wowhead\.com|fr\.wowhead\.com|ru\.wowhead\.com)\/guild=(us|eu)\.([^\.]+)\.([^\.#]+).*/i,
			'params': {
				'1': 'wowheadhost',
				'2': 'region',
				'3': 'realm',
				'4': 'guild'
			}
		}
	},

	'queries': {
		'guild': {
			'required' : true,
			'condition': true,
			'call'     : 'http://<%= this["host"] %>/api/wow/guild/<%= this["realm"] %>/<%= this["guild"] %>?fields=members&locale=<%= this["locale"] %>'
		}
	},
	
	'getParams': {
		'implicit': function(result) {
			var params       = DarkTip.mapRegex(result, DarkTip._read(DarkTip.route('wow.wowhead.guild', 'triggers.implicit.params')));
			params['lang']   = DarkTip.map('wow.wowhead', 'maps.wowheadhost.lang', params['wowheadhost']);
			params['host']   = DarkTip.map('wow', 'maps.region.host', params['region']);
			params['locale'] = DarkTip.map('wow', 'maps.region+lang.locale', (params['region'] + '+' + params['lang']));
			return params;
		}
	},

	'layout': {
		'width': {
			'core': 275
		}
	},

	'templates': {
		'core': (
			'<div class="tooltip-guild">' +
				'<div class="headline-right"><span class="icon-achievenemtpoints"><%= this["guild"]["achievementPoints"] %></span></div>' +
    			'<div class="darktip-row headline cside-<%= this["guild"]["side"] %>"><%= this["guild"]["name"] %></div>' +
	    		'<div class="darktip-row highlight-medium"><%= this._loc("classification") %></div>' +
				'<% if(this["guild"]["members"]) { %><div class="darktip-row"><%= this._loc("members") %></div><% } %>' +
			'</div>'
		),
		'404': (
			'<div class="tooltip-guild tooltip-404">' +
				'<div class="title">404<span class="sub"> / <%= this._loc("not-found") %></span></div>' +
				'<div class="darktip-row"><span class="label"><%= this._loc("label.guild") %></span> <span class="value"><%= this["guild"] %></span></div>' +
				'<div class="darktip-row"><span class="label"><%= this._loc("label.realm") %></span> <span class="value"><%= this["realm"] %></span></div>' +
				'<div class="darktip-row"><span class="label"><%= this._loc("label.region") %></span> <span class="value"><%= this["region"] %></span></div>' +
		    '</div>'
		)
	},

	'i18n': {
		'en_US': {
			'loading'       : 'Loading Guild...',
			'not-found'     : 'Guild not found',
			'classification': 'Level <%= this["guild"]["level"] %> <%= this._loc("factionSide." + this["guild"]["side"]) %> Guild, <%= this["guild"]["realm"] %>',
			'members'       : '<%= this["guild"]["members"].length %> members'
		},
		'de_DE': {
			'loading'       : 'Lade Gilde...',
			'not-found'     : 'Gilde nicht gefunden',
			'classification': 'Stufe <%= this["guild"]["level"] %> <%= this._loc("factionSide." + this["guild"]["side"]) %>-Gilde, <%= this["guild"]["realm"] %>',
			'members'       : '<%= this["guild"]["members"].length %> Mitglieder'
		},
		'fr_FR': {
			'loading'       : 'Chargement Guilde...',
			'not-found'     : 'Aucune Guilde trouvée',
			'classification': 'Niveau <%= this["guild"]["level"] %> Faction <%= this._loc("factionSide." + this["guild"]["side"]) %> , <%= this["guild"]["realm"] %>',
			'members'       : '<%= this["guild"]["members"].length %> membres'
		},
		'es_ES': {
			'loading'       : 'Cargando hermandad...',
			'not-found'     : 'Hermandad no encontrada',
			'classification': 'Hermandad (<%= this._loc("factionSide." + this["guild"]["side"]) %>), nivel <%= this["guild"]["level"] %>, <%= this["guild"]["realm"] %>',
			'members'       : '<%= this["guild"]["members"].length %> miembros'
		}
	}
	
});