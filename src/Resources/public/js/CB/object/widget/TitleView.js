Ext.namespace('CB.object.widget');

Ext.define('CB.object.TitleView', {
    extend: 'Ext.DataView'
    ,allowTitleWrap: true
    ,initComponent: function() {
        var titleBlock = '<b class="{[this.textCls]} {titleCls}">{[ Ext.String.htmlEncode(Ext.valueFrom(values.name, \'\')) ]}' +
                    '<span class="obj-fav click {[ this.getFavIcon(values) ]}"></span></b>';

        if (!this.allowTitleWrap) {
            titleBlock = '<b class="title-text-nowrap {titleCls}"><span class="obj-fav click {[ this.getFavIcon(values) ]}"></span>' +
                '{[ Ext.String.htmlEncode(Ext.valueFrom(values.name, \'\')) ]}</b>';
        }

        this.tpl = new Ext.XTemplate(
            '<tpl for=".">'
            ,'<table class="obj-header">'
                ,'<tr>'
                ,'<td><span class="obj-icon {[ getItemIcon(values) ]}"></span></td>'
                ,'<td>' + titleBlock
                ,'{[ this.getStatusInfo(values) ]}'
                ,'<div class="info">'
                    ,'{[ this.getTitleInfo(values) ]}'
                ,'</div>'
                ,'</td>'
                ,'</tr>'
            ,'</table>'
            ,'<div class="obj-details" style="display: none"></div>'
            ,'</tpl>'
            ,{
                textCls: this.textCls
                ,getStatusInfo: this.getStatusInfo
                // ,getPath: this.getPath
                ,getFavIcon: this.getFavIcon
                ,getTitleInfo: this.getTitleInfo
            }
        );

        Ext.apply(this, {
            autoHeight: true
            ,cls: 'obj-plugin-title'
            ,itemSelector: '.none'
            ,data: Ext.valueFrom(this.config.data, {})
            ,listeners: {
                scope: this
                ,containerclick: this.onContainerClick
            }
        });

        this.callParent(arguments);

        App.Favorites.on('change', this.onFavoritesChange, this);
    }

    /**
     * get status info displayed next to the title
     * @return string
     */
    ,getStatusInfo: function (values) {
        if(Ext.isEmpty(values.status)) {
            return '';
        }

        var rez = '<div class="dIB fs12 ' + Ext.valueFrom(values.statusCls, '') + '"">' +
            values.status + '</div>';

        return rez;
    }

    /**
     * get path
     * @return string
     */
    ,getPath: function (values) {
        if(Ext.isEmpty(values.path)) {
            return '';
        }

        var rez = '<a class="click locate-path" title="' + values.path + '">' +
            App.shortenStringLeft(values.path, 50) + '</a>';

        return rez;
    }

    ,getFavIcon: function (values) {
        var rez = 'fa fa-star-o';
        if(App.Favorites.isStarred(values.id)) {
            rez = 'fa fa-star';
        }

        return rez;
    }

    /**
     * get info displayed under the title
     * Ex: TemplateType &#8226; #{id} &#8226; Ubdate by <a href="#">user name</a> time ago
     * @return string
     */
    ,getTitleInfo: function (values) {
        var rez = [];

        // #Id
        if(values.id) {
            rez.push('#' + values.id);
        }

        // Template
        rez.push(CB.DB.templates.getName(values.template_id));
        rez.push('<a class="obj-details click">' + L.Details + ' <span class="fa fa-angle-down"></span></a>');

        return rez.join(' &#8226; ');
    }

    ,onContainerClick: function(view, e, eOpts) {
        if(e) {
            var el = e.getTarget('.path');
            if(el) {
                this.hideDetailsPanel();
                return App.openPath(this.data.pids);
            }

            el = e.getTarget('.obj-icon');
            if(el) {
                return this.onObjectIconClick();
            }

            el = e.getTarget('.obj-fav');
            if(el) {
                return this.toggleFavorite();
            }

            el = e.getTarget('a.obj-details');
            if(el) {
                return this.showDetails(el);
            }

            el = e.getTarget('a.edit-template');
            if(el) {
                return this.editTemplate();
            }

            el = e.getTarget('a.user-popup');
            if(el) {
                return this.showUserPopupMenu(el.attributes.getNamedItem('data-id').value, e.getXY());
            }

            el = e.getTarget('a.locate-path');
            if(el) {
                this.hideDetailsPanel();
                return App.controller.openPath({
                    path: String(this.data.pids).replace('.', '/')
                    ,filters:[]
                });
            }

            el = e.getTarget('.edit-config');
            if(el) {
                return this.editConfig();
            }
        }
    }

    ,onObjectIconClick: function() {
        clog('onObjectIconClick');
    }

    ,toggleFavorite: function() {
        var d = this.data;

        if (App.Favorites.isStarred(d.id)) {
            App.Favorites.setUnstarred(d.id);
        } else {
            var data = {
                id: d.id
                ,name: d.name
                ,iconCls: d.iconCls
                ,path: '/' + d.pids + '/' + d.id
                ,pathText: d.path
            };

            App.Favorites.setStarred(d);
        }
    }

    ,onFavoritesChange: function(favCmp) {
        if(this.data) {
            var isStarred = App.Favorites.isStarred(this.data.id)
                ,el = this.getEl().down('.obj-fav');

            if(el) {
                if(isStarred) {
                    el.replaceCls('fa-star-o', 'fa-star');
                } else {
                    el.replaceCls('fa-star', 'fa-star-o');
                }
            }
        }
    }

    ,showDetails: function(el) {
        var icon = Ext.get(el).down('span')
            ,div = this.getEl().down('div.obj-details');

        if(div) {
            if(div.isVisible()) {
                div.set({style: 'display: none'});
                icon.replaceCls('fa-angle-up', 'fa-angle-down');
                this.hideDetailsPanel();
            } else {
                if (this.floatingDetails) {
                    var e = this.getEl()
                        ,panel = this.getFloatingPanel();

                    panel.setWidth(Math.min(this.getWidth(), 500));
                    panel.update(this.getDetailsHtml());
                    panel.showAt(e.getX(), e.getY() + e.getHeight() + 3);
                } else {
                    div.setHtml(this.getDetailsHtml());
                }

                div.show();
                icon.replaceCls('fa-angle-down', 'fa-angle-up');
            }
            this.updateLayout();
        }
    }

    ,hideDetailsPanel: function() {
        if (this.floatingDetails) {
            this.getFloatingPanel().hide();
        }
    }

    ,getFloatingPanel: function() {
        if(!this.detailsPanel) {
            this.detailsPanel = Ext.create({
                xtype: 'panel'
                ,title: L.Details
                ,cls: 'obj-plugin-title'
                ,closeAction: 'hide'
                ,width: 100
                ,autoHeight: true
                ,html: ''
                ,floating: true
                ,closable: true
                ,listeners: {
                    scope: this
                    ,afterrender: function(p) {
                        p.getEl().on(
                            'click'
                            ,function(ev, el, eOpts) {
                                this.onContainerClick(this, ev, eOpts);
                            }
                            ,this);
                    }
                    ,close: function(p, eOpts) {
                        this.showDetails(this.getEl().down('a.obj-details'));
                    }
                }
            });
        }

        return this.detailsPanel;
    }

    ,editTemplate: function() {
        App.windowManager.openObjectWindowById(this.data.template_id, {view: 'edit'});
        this.hideDetailsPanel();
    }

    ,onViewUserProfileClick: function() {
        App.windowManager.openObjectWindowById(this.userMenu.userId);
        this.hideDetailsPanel();
    }

    ,showUserPopupMenu: function(userId, coordinates) {
        if (!this.userMenu) {
            this.userMenu = Ext.create('Ext.menu.Menu' ,{
                items: [
                    {
                        text: L.Message
                        ,disabled: true
                    },{
                        text: L.Profile
                        ,scope: this
                        ,handler: this.onViewUserProfileClick
                    }

                ]
            });
        }

        this.userMenu.userId = userId;
        this.userMenu.showAt(coordinates);
    }

    ,editConfig: function() {
        var editor = new CB.TextEditWindow({
            title: L.Config
            ,editor: 'ace'
            ,editorOptions: {
                mode: 'ace/mode/json'
            }
            ,data: {
                value: this.data.cfg
                ,scope: this
                ,callback: this.editConfigCallback
            }
        });
        editor.show();
    }

    ,editConfigCallback: function(w, v){
        this.data.cfg = v;

        CB_Objects.updateRecordConfig(
            {
                id: this.data.id
                ,cfg: v
            }
            ,this.onUpdateRecordConfig
            ,this
        );
    }

    ,onUpdateRecordConfig: function(r, e) {
        if (r.success !== true) {
            return Ext.Msg.alert(L.Error, Ext.valueFrom(r.msg, L.ErroneousInputData));
        }

        if (this.floatingDetails) {
            var panel = this.getFloatingPanel();

            panel.update(this.getDetailsHtml());
        } else {
            var div = this.getEl().down('div.obj-details');
            div.setHtml(this.getDetailsHtml());
            this.updateLayout();
        }
    }

    ,getDetailsHtml: function () {
        var d = this.data
            ,sizeRow = Ext.isEmpty(d.size)
                ? ''
                : '<tr><td class="k">' + L.Size + '</td><td>' + App.customRenderers.filesize(d.size) + '</td></tr>'
            ,templateName = CB.DB.templates.getName(d.template_id)
            ,deletedRow = Ext.isEmpty(d.ddate)
                ? ''
                : '<tr><td class="k">' + L.Deleted + '</td><td>' + d.did_text + '<br><span class="dttm" title="' + displayDateTime(d.ddate) + '">' + d.ddate_text + '</span></td></tr>'
            ,nodeConfig = Ext.util.Format.nl2br(Ext.String.htmlEncode(Ext.valueFrom(d.cfg, '')).replace(/ /g, '&nbsp;').replace(/\t/g, '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'))
            ,versionsRow = (Ext.isEmpty(d.versions) || (d.versions < 1))
                ? ''
                : '<tr><td class="k">' + L.Versions + '</td><td><span class="fa fa-history"> ' + d.versions + '</span> <a class="click manage-versions">' + L.Manage + '</a></td></tr>'

            ,rez = '<table class="item-props">' +
            '<tbody><tr><td class="k">Id</td><td>' + d.id + '</td></tr>' +
            '<tr><td class="k">'+L.Type+'</td><td><a class="click edit-template">' + templateName + ' <span class="dttm">(id: ' + d.template_id + ')</span></a></td></tr>' +
            sizeRow +
            '<tr><td class="k">'+L.Location+'</td><td>' + this.getPath(d) + '</td></tr>' +
            '<tr><td class="k">'+L.Created+'</td><td><span class="dttm" title="' + displayDateTime(d.cdate) + '">' + d.cdate_ago_text + '</span> ' +
                L.by + ' <a class="click user-popup" data-id="' + d.cid+ '">' + CB.DB.usersStore.getName(d.cid) + '</a></td></tr>' +
            '<tr><td class="k">'+L.Modified+'</td><td><span class="dttm" title="' +  displayDateTime(d.udate) + '">' + d.udate_ago_text + '</span> ' +
                L.by + ' <a class="click user-popup" data-id="' + d.uid+ '">' + CB.DB.usersStore.getName(d.uid) + '</a></td></tr>' +
            deletedRow +
            '<tr><td class="k">'+L.Config+'</td><td><span class="edit-config click fa fa-info-circle"></span>' + nodeConfig + '</td></tr>' +
            versionsRow +
            '</tbody></table>';

        return rez;
    }
});
