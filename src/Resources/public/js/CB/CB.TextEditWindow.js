Ext.namespace('CB');

Ext.define('CB.TextEditWindow', {
    extend: 'Ext.Window'
    ,border: false
    ,bodyBorder: false
    ,closable: true
    ,closeAction: 'destroy'
    ,hideCollapseTool: true
    ,layout: 'fit'
    ,maximizable: false
    ,minimizable: false
    ,modal: true
    ,resizable: true
    ,stateful: false
    ,data: { callback: Ext.emptyFn }
    ,title: L.EditingValue
    ,width: 600
    ,height: 400

    ,initComponent: function() {
        this.data = this.config.data;

        switch(this.config.editor) {
            case 'ace':
                this.editor = new Jarvus.ace.Editor({
                    border: false
                });
                break;

            default:
                this.editor = new Ext.form.TextArea({border: false});
        }

        Ext.apply(this, {
            layout: 'fit'
            ,items: [this.editor]
            ,keys:[{
                key: Ext.event.Event.ESC,
                fn: this.doClose,
                scope: this
                }
            ]
            ,listeners: {
                scope: this
                ,show: this.onWindowsShow
            }
            ,buttons: [
                {
                    text: Ext.MessageBox.buttonText.ok
                    ,handler: this.doSubmit
                    ,scope: this
                },{
                    text: L.Cancel
                    ,handler: this.doClose
                    ,scope: this
                }
            ]
        });

        this.callParent(arguments);
    }

    ,onWindowsShow: function(){
        //update title if set
        var title = Ext.valueFrom(this.data.title, this.title);
        this.setTitle(title);
        this.getHeader().setTitle(title);

        var ed = this.editor.getAce
            ? this.editor.getAce()
            : this.editor;

        ed.setValue(
            Ext.valueFrom(this.data.value, '')
        );
        var options = Ext.valueFrom(this.config.editorOptions, {});
        if(!Ext.isEmpty(this.config.highlighter)) {
            options.mode = this.config.highlighter;
        }

        if (!Ext.isEmpty(options) && this.editor.getConfiguration) {
            var cfg = this.editor.getConfiguration();
            Ext.Object.each(
                options
                ,function(k, v, o) {
                    cfg.setOption(k, v);
                }
                ,this
            );
        }
        ed.focus(false, 350);
    }

    ,doSubmit: function(){
        var ed = this.editor.getAce
                ? this.editor.getAce()
                : this.editor
            ,session = ed.getSession
                ? ed.getSession()
                : null
            ,value = session
                ? session.getValue()
                : ed.getValue()
            ,f = Ext.Function.bind(
                this.data.callback
                ,Ext.valueFrom(this.data.scope, this)
                ,[this, value]
            );

        f();

        this.close();
    }
});
