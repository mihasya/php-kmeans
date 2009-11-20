<?php
    #
    # $Id$
    #
    # $k - the number of clusters; duh
    # $input - the array of values/arrays/objects that needs to be clustered
    # $attribute - (optional) the key to cluster the objects/arrays on
    # $index - the index (in the $input array) of a particular value
    # $values - the mapping of $index to $attribute (or value)
    #
    # $cluster_map - mapping of array indexes to cluster numbers
    # $clusters - an actual grouping of $index => $values into clusters (arrays)
    # $centroids - the array of centroid values for clusters
    #

    #
    # the function that actually does our shit
    #
    function kmeans(&$input, $k, $attribute = null){
        if(empty($input)){
            return array();
        }

        #
        # if we're dealing with scalars, then just take them as is; otherwise,
        # extract just the values of interest
        #
        $values = $attribute ? kmeans_values($input, $attribute) : $input;

        # setup
        $cluster_map = array();
        $centroids = kmeans_initial_centroids($values, $k);

        #
        # warning: this is recursive...
        #
        $clusters = kmeans_cluster($values, $cluster_map, $centroids);

        return $attribute ? kmeans_rebuild($input, $clusters, $attribute) : $clusters;
    }

    #
    # perform the actual clustering
    #
    function kmeans_cluster(&$values, &$cluster_map, &$centroids){
        $num_changes = 0;

        foreach ($values as $index => $value){
            $min_distance = null;
            $new_cluster = null;
            foreach ($centroids as $cluster_index => $centroid){
                $distance = abs($value - $centroid);
                if (is_null($min_distance) || $min_distance > $distance){
                    $min_distance = $distance;
                    $new_cluster = $cluster_index;
                }
            }
            if (!isset($cluster_map[$index]) || $new_cluster != $cluster_map[$index]){
                $num_changes++;
            }
            $cluster_map[$index] = $new_cluster;
        }

        $clusters = kmeans_populate_clusters($values, $cluster_map);

        #
        # TODO: we probably want to be able to get out of the clustering
        # sooner, otherwise we may be here all day.
        #
        # perhaps maintain state and keep track of how many iterations we've
        # been through vs how many changes are coming out of each successive iteration.
        # wouldn't want an infinite recursion or anything...
        #
        if ($num_changes){   
            $centroids = kmeans_recalculate_centroids($clusters, $centroids);
            kmeans_cluster($values, $cluster_map, $centroids);
        }

        return $clusters;
    }

    #
    # figure out centroids (means) for the clusters as they are
    #
    function kmeans_recalculate_centroids($clusters, $centroids){

        foreach ($clusters as $cluster_index => $cluster){
            $cluster_values = array_values($cluster);
            $count = count($cluster_values);
            $mean = $count ? array_sum($cluster_values) / $count : 0;
            if ($centroids[$cluster_index] != $mean){
                $centroids[$cluster_index] = $mean;
            }
        }

        return $centroids;
    }

    #
    # set up some reasonable defaults for centroid values
    #
    function kmeans_initial_centroids(&$values, $k){
        $centroids = array();
        $max = max($values);
        $min = min($values);
        $interval = ceil(($max-$min) / $k);

        while (0 <= --$k){
            $centroids[$k] = $min + $interval * $k; 
        }

        return $centroids;
    }

    #
    # in the event that we're dealing with an array of objects, extract just a
    # key => value of interest mapping first
    #
    function kmeans_values(&$input, $attribute){
        $values = array();

        foreach ($input as $index => $value){
            $value = (array)$value;
            $values[$index] = $value[$attribute];
        }

        return $values;
    }

    #
    # convert the $index => $cluster_index map to a $cluster_index => $cluster map
    # ($cluster is a $index => $value mapping)
    #
    function kmeans_populate_clusters(&$values, &$cluster_map){
        $clusters = array();
        foreach ($cluster_map as $index => $cluster){
            $clusters[$cluster][$index] = $values[$index];
        }

        return $clusters;
    }

    #
    # if we're dealing with non-scalars, re-attach the actual objects to their
    # indexes in the clusters, and populate the objects with useful cluster info
    #
    function kmeans_rebuild(&$input, &$clusters, $attribute){
        if ($attribute){
            $cluster_key = "cluster_{$attribute}";
            $cluster_size_key = "cluster_size_{$attribute}";
            $clusters_rebuilt = array();
            foreach ($clusters as $cluster_index =>$cluster){
                $cluster_size = count($cluster);
                foreach ($cluster as $index => $value){
                    if (is_array($input[$index])){
                        $input[$index][$cluster_key] = $cluster_index;
                        $input[$index][$cluster_size_key] = $cluster_size;
                    }else{
                        $input[$index]->$cluster_key = $cluster_index;
                        $input[$index]->$cluster_size_key = $cluster_size;
                    }
                    $clusters_rebuilt[$cluster_index][$index] = $input[$index];
                }
            }
        }else{
            $clusters_rebuilt = $clusters;
        }

        return $clusters_rebuilt;
    }

    ########### TESTS #######################

    require_once 'PHPUnit/Framework.php';

    class StackTest extends PHPUnit_Framework_TestCase {
        function test_kmeans_values_arrays(){
            $input = array(
                array('fluff' => 5, 'baz' => 'barf'),
                array('fluff' => 1, 'horse' => 'ham'),
            );
            $values = kmeans_values($input, 'fluff');
            
            $expected = array(
                5,
                1,
            );
            $this->assertEquals($expected, $values);
        }

        function test_kmeans_values_objects(){
            $input = array(
                (object)array('fluff' => 5, 'baz' => 'barf'),
                (object)array('fluff' => 1, 'horse' => 'ham'),
            );
            $values = kmeans_values($input, 'fluff');
            
            $expected = array(
                5,
                1,
            );
            $this->assertEquals($expected, $values);
        }
        
        function test_kmeans_initial_centroids(){
            $values = array(
                5, 6, 7, 8, 9
            );
            $centroids = kmeans_initial_centroids($values, 5);
            $expected = array(5, 6, 7, 8, 9);
            $this->assertEquals($expected, $centroids);
        }
        
        function test_kmeans_populate_clusters(){
            $values = array(1,2,3,4,5);
            $cluster_map = array(
                0 => 2,
                1 => 4,
                2 => 3,
                3 => 0,
                4 => 1,
            );
            $expected = array(
                0 => array(3=>4),
                1 => array(4=>5),
                2 => array(0=>1),
                3 => array(2=>3),
                4 => array(1=>2),
            );
            
            $clusters = kmeans_populate_clusters($values, $cluster_map);
            foreach ($clusters as $key => $value){
                $this->assertEquals($expected[$key], $value);
            }

        }

        function test_kmeans_empty_set(){
            $input = array();
            $k = 5;
            $clusters = kmeans($input, $k);
            $this->assertEquals(array(), $clusters);
        }

        #
        # test that centroids get calculated correctly (to 0) if there are empty
        # clusters
        #
        function test_kmeans_recalculate_centroids_homogenous(){
            $input = array(1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);
            $k = 3;
            $centroids = kmeans_initial_centroids($input, $k);
            $clusters = array(array(1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1), array(), array());
            $centroids = kmeans_recalculate_centroids($clusters, $centroids);
            $expected = array(1, 0, 0);
            $this->assertEquals($expected, $centroids);
        }

        function test_kmeans_homogenous(){
            $input = array(1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1);
            $k = 3;
            $clusters = kmeans($input, $k);
            $this->assertEquals(1, count($clusters));
        }

        #
        # these next tests are pretty much functional tests - as in, 
        # I ran it, made sure it did the right thing, var_exported the result
        # and put that as the expected value, so that if that ever changes,
        # I know I fucked something up.
        #
        function test_kmeans_scalars(){
            $input = array(1, 3, 2, 5, 6, 2, 3, 1, 30, 36, 45, 3, 15, 17);
            $k =  3;
            $clusters = kmeans($input, $k);
            $expected = array (
              0 => array (
                0 => 1,
                1 => 3,
                2 => 2,
                3 => 5,
                4 => 6,
                5 => 2,
                6 => 3,
                7 => 1,
                11 => 3,
              ),
              2 => array (
                8 => 30,
                9 => 36,
                10 => 45,
              ),
              1 => array (
                12 => 15,
                13 => 17,
              ),
            );
            $this->assertEquals($expected, $clusters);
        }
        
        function test_kmeans_arrays(){
            $input = array(
                array('age' => 1),
                array('age' => 3),
                array('age' => 2),
                array('age' => 5),
                array('age' => 6),
                array('age' => 2),
                array('age' => 3),
                array('age' => 1),
                array('age' => 30),
                array('age' => 36),
                array('age' => 45),
                array('age' => 3),
                array('age' => 15),
                array('age' => 17),
            );
            $k = 3;
            $clusters = kmeans($input, $k, 'age');
            $expected = array (
              0 => array (
                0 => array (
                  'age' => 1,
                  'cluster_age' => 0,
                  'cluster_size_age' => 9,
                ),
                1 => array (
                  'age' => 3,
                  'cluster_age' => 0,
                  'cluster_size_age' => 9,
                ),
                2 => array (
                  'age' => 2,
                  'cluster_age' => 0,
                  'cluster_size_age' => 9,
                ),
                3 => array (
                  'age' => 5,
                  'cluster_age' => 0,
                  'cluster_size_age' => 9,
                ),
                4 => array (
                  'age' => 6,
                  'cluster_age' => 0,
                  'cluster_size_age' => 9,
                ),
                5 => array (
                  'age' => 2,
                  'cluster_age' => 0,
                  'cluster_size_age' => 9,
                ),
                6 => array (
                  'age' => 3,
                  'cluster_age' => 0,
                  'cluster_size_age' => 9,
                ),
                7 => array (
                  'age' => 1,
                  'cluster_age' => 0,
                  'cluster_size_age' => 9,
                ),
                11 => array (
                  'age' => 3,
                  'cluster_age' => 0,
                  'cluster_size_age' => 9,
                ),
              ),
              2 => array (
                8 => array (
                  'age' => 30,
                  'cluster_age' => 2,
                  'cluster_size_age' => 3,
                ),
                9 => array (
                  'age' => 36,
                  'cluster_age' => 2,
                  'cluster_size_age' => 3,
                ),
                10 => array (
                  'age' => 45,
                  'cluster_age' => 2,
                  'cluster_size_age' => 3,
                ),
              ),
              1 => array (
                12 => array (
                  'age' => 15,
                  'cluster_age' => 1,
                  'cluster_size_age' => 2,
                ),
                13 => array (
                  'age' => 17,
                  'cluster_age' => 1,
                  'cluster_size_age' => 2,
                ),
              ),
            );
            $this->assertEquals($expected, $clusters);
        }
    }

?>