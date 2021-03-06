
/* vim: set expandtab tabstop=2 shiftwidth=2 softtabstop=2 cc=80; */

/**
 * @package     omeka
 * @subpackage  fedora-connector
 * @copyright   2012 Rector and Board of Visitors, University of Virginia
 * @license     http://www.apache.org/licenses/LICENSE-2.0.html
 */


module.exports = function(grunt) {

  grunt.loadNpmTasks('grunt-contrib-concat');
  grunt.loadNpmTasks('grunt-contrib-uglify');
  grunt.loadNpmTasks('grunt-contrib-clean');
  grunt.loadNpmTasks('grunt-contrib-compress');
  grunt.loadNpmTasks('grunt-contrib-watch');
  grunt.loadNpmTasks('grunt-phpunit');
  grunt.loadNpmTasks('grunt-contrib-jasmine');
  grunt.loadNpmTasks('grunt-contrib-connect');
  grunt.loadNpmTasks('grunt-bower-task');

  var pkg = grunt.file.readJSON('package.json');
  var paths = grunt.file.readJSON('paths.json');

  grunt.initConfig({

    bower: {
      install: {
        options: {
          copy: false
        }
      }
    },

    clean: {

      fixtures: [
        paths.jasmine+'/fixtures/*.json',
        paths.jasmine+'/fixtures/*.html'
      ],

      payloads: paths.payloads,
      bower: 'bower_components',
      pkg: 'pkg'

    },

    concat: {

      datastreams: {
        src: [
          paths.vendor.underscore,
          paths.vendor.backbone,
          paths.vendor.marionette,
          paths.src+'/*.js',
          paths.src+'/datastreams/*.js'
        ],
        dest: paths.payloads+'/datastreams.js'
      }

    },

    uglify: {

      datastreams: {
        src:  '<%= concat.datastreams.src %>',
        dest: '<%= concat.datastreams.dest %>'
      }

    },

    watch: {

      datastreams: {
        files: paths.src+'/**/*.js',
        tasks: 'concat'
      }

    },

    phpunit: {

      options: {
        bin: 'vendor/bin/phpunit',
        bootstrap: 'tests/phpunit/bootstrap.php',
        followOutput: true,
        colors: true
      },

      application: {
        dir: 'tests/phpunit'
      }

    },

    jasmine: {

      options: {
        helpers: [
          paths.vendor.jquery,
          paths.vendor.jasmine_jquery,
          paths.vendor.sinon,
          paths.jasmine+'/helpers/*.js'
        ]
      },

      datastreams: {
        src: paths.payloads+'/datastreams.js',
        options: {
          specs: paths.jasmine+'/integration/**/*.spec.js'
        }
      }

    },

    connect: {

      server: {
        options: {
          keepalive: true,
          port: 1337
        }
      }

    },

    compress: {

      dist: {
        options: {
          archive: 'pkg/FedoraConnector-'+pkg.version+'.zip'
        },
        dest: 'FedoraConnector/',
        src: [

          '**',

          // GIT
          '!.git/**',

          // BOWER
          '!bower.json',
          '!bower_components/**',

          // NPM
          '!package.json',
          '!node_modules/**',

          // COMPOSER
          '!composer.json',
          '!composer.lock',
          '!composer.phar',
          '!vendor/**',

          // RUBY
          '!Gemfile',
          '!Gemfile.lock',
          '!Rakefile',

          // GRUNT
          '!.grunt/**',
          '!Gruntfile.js',
          '!paths.json',

          // DIST
          '!pkg/**',

          // TESTS
          '!tests/**'

        ]
      }

    }

  });

  // Run tests by default.
  grunt.registerTask('default', 'test');

  // Build the application.
  grunt.registerTask('build', [
    'clean',
    'bower',
    'uglify'
  ]);

  // Spawn a release package.
  grunt.registerTask('package', [
    'uglify',
    'clean:pkg',
    'compress'
  ]);

  // Mount Jasmine tests for browser.
  grunt.registerTask('jasmine:server', [
    'jasmine:datastreams:build',
    'connect'
  ]);

  // Run all test suites.
  grunt.registerTask('test', [
    'uglify',
    'phpunit',
    'jasmine'
  ]);

};
